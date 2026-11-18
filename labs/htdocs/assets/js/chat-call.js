/**
 * Wrapped with IIFE Error Boundary
 */
try {
  (function() {
    "use strict";

/**
 * ============================================================================
 * TOM LABS — GLOBAL CHAT: VIDEO CALL MODULE (chat-call.js)
 * ============================================================================
 * Split from chat.js so the admin can control delivery:
 *   - Admin → Settings → Chat Settings → video_call_mode (src/config/chat.json)
 *   - "off"    : this file is NOT loaded and the call UI is NOT rendered —
 *                no call code reaches the browser at all.
 *   - "admin"  : loaded for everyone, but only admins get the call button
 *                (server enforces offers; default mode).
 *   - "public" : loaded for logged-in users; anyone may start calls.
 *
 * The real security boundary is server-side (call-signal.php + ChatSettings);
 * this module is UI only. It talks to chat.js through window.ChatBridge
 * (csrfHeaders / isAdmin / myUserId / onlineCount) because chat.js keeps its
 * state inside a closure.
 *
 * Depends on: chat.js (must load AFTER it), app.css (call styles)
 * @author Sathish46 - Tom Labs
 * ============================================================================
 */

const call = {
  active: false,
  callId: null,
  pc: null,
  localStream: null,
  remoteStream: null,
  incoming: false,
  offerFrom: null,
  offerSdp: null,
  offerType: null,
  remoteName: null,       // display name of the remote peer (from signaling events)
  iceBuffer: [],          // incoming ICE candidates buffered until setRemoteDescription
  iceReady: false,        // true once setRemoteDescription has completed
  ringTimer: null,        // 45s ringing timeout
};

/* ==========================================================================
 * Video call (admin-initiated, P2P WebRTC)
 * Signaling rides the existing socket topic via /api/chat/call-signal.php
 * ========================================================================= */

const CALL_STUN = [{ urls: "stun:stun.l.google.com:19302" }];
const CALL_SIGNAL_URL = "/api/chat/call-signal.php";

// NOTE: No TURN server is configured. Calls across strict NATs (symmetric
// NAT, carrier-grade NAT) will fail to establish a media path. Add TURN
// servers to CALL_STUN if you need to traverse those networks.

function callEls() {
  return {
    modal: document.getElementById("chat-call-modal"),
    incoming: document.getElementById("chat-call-incoming"),
    localVideo: document.getElementById("chat-call-local-video"),
    remoteVideo: document.getElementById("chat-call-remote-video"),
    localLabel: document.getElementById("chat-call-local-label"),
    remoteLabel: document.getElementById("chat-call-remote-label"),
    fullscreenBtn: document.getElementById("chat-call-fullscreen"),
    status: document.getElementById("chat-call-status"),
    endBtn: document.getElementById("chat-call-end"),
    acceptBtn: document.getElementById("chat-call-accept"),
    declineBtn: document.getElementById("chat-call-decline"),
    fromLabel: document.getElementById("chat-call-incoming-from"),
    error: document.getElementById("chat-call-error"),
    errorTitle: document.getElementById("chat-call-error-title"),
    errorDesc: document.getElementById("chat-call-error-desc"),
    dismissBtn: document.getElementById("chat-call-dismiss-error"),
    retryBtn: document.getElementById("chat-call-retry-perm"),
  };
}

// The admin can flip the call mode to "off" while a browser still shows a
// stale call button. The backend is the source of truth: when a signal
// request comes back 403, surface a toast so the user knows why nothing
// happens (shown once per page load so ICE candidates can't spam it).
let disabledToastShown = false;

function showDisabledToast(errorText) {
  if (disabledToastShown) return;
  disabledToastShown = true;
  const msg = errorText || "Video calls are disabled by the admin";
  if (window.TomNotify) TomNotify.show(msg, "Notice", "info", 5000);
  console.warn("[ChatCall] calls disabled by admin —", msg);
}

async function publishSignal(type, payload) {
  try {
    const res = await fetch(CALL_SIGNAL_URL, {
      method: "POST",
      credentials: "same-origin",
      headers: { ...window.ChatBridge.csrfHeaders(), "content-type": "application/json" },
      body: JSON.stringify({ type, ...payload }),
    });
    if (!res.ok) {
      const body = await res.json().catch(() => ({}));
      console.warn("[Chat] signal publish HTTP error:", res.status, body.error || res.statusText);
      // 403 = the admin changed the call mode (or this user lost permission)
      // after this page was loaded — tell the user instead of failing silently.
      if (res.status === 403) showDisabledToast(body.error);
      return { ok: false, status: res.status, error: body.error || res.statusText };
    }
    const data = await res.json().catch(() => ({}));
    if (!data.success) {
      console.warn("[Chat] signal publish API error:", data.error);
      return { ok: false, status: res.status, error: data.error };
    }
    console.log("[Chat] signal published:", type, "callId=", payload.call_id);
    return { ok: true };
  } catch (e) {
    console.warn("[Chat] call signal failed:", e);
    return { ok: false, error: e.message };
  }
}

function showCallModal() {
  const { modal } = callEls();
  if (!modal) return;
  call.active = true;
  modal.hidden = false;
  modal.classList.add("is-open");
  callEls().endBtn?.addEventListener("click", endCall);
  callEls().fullscreenBtn?.addEventListener("click", toggleCallFullscreen);
  console.log("[Chat] call modal opened");
}

function hideCallModal() {
  const { modal, localVideo, remoteVideo, localLabel, remoteLabel } = callEls();
  if (modal) {
    modal.hidden = true;
    modal.classList.remove("is-open");
    modal.classList.remove("is-fullscreen");
  }
  call.active = false;
  if (localVideo) { localVideo.srcObject = null; }
  if (remoteVideo) { remoteVideo.srcObject = null; }
  if (localLabel) localLabel.textContent = "You";
  if (remoteLabel) remoteLabel.textContent = "Waiting…";
  hideCallError();
  callEls().endBtn?.removeEventListener("click", endCall);
  callEls().fullscreenBtn?.removeEventListener("click", toggleCallFullscreen);
  console.log("[Chat] call modal closed");
}

function toggleCallFullscreen() {
  const { modal, fullscreenBtn } = callEls();
  if (!modal) return;
  const isFs = modal.classList.toggle("is-fullscreen");
  if (fullscreenBtn) fullscreenBtn.textContent = isFs ? "⤡" : "⛶";
  console.log("[Chat] call fullscreen:", isFs);
}

/* Status line for the call modal. Shows the connection state plus, for
   admins, how many users are in the call vs how many are online
   (e.g. "Connected · 2/10 · 10 online"). */
function updateCallStatusText() {
  const { status } = callEls();
  if (!status) return;
  const pc = call.pc;
  const state = !pc
    ? "Starting call…"
    : (pc.connectionState === "connected" ? "Connected" : (pc.connectionState || "Connecting…"));
  if (window.ChatBridge.isAdmin() && window.ChatBridge.onlineCount() !== null) {
    const inCall = pc && pc.connectionState === "connected" ? 2 : 1;
    status.textContent = `${state} · ${inCall}/${window.ChatBridge.onlineCount()} · ${window.ChatBridge.onlineCount()} online`;
  } else if (pc) {
    status.textContent = pc.connectionState === "connected" ? "Connected" : (pc.connectionState || "Connecting…");
  } else {
    status.textContent = "Starting call…";
  }
}

function stopLocalStream() {
  if (call.localStream) {
    call.localStream.getTracks().forEach((t) => t.stop());
    call.localStream = null;
  }
}

function openSiteSettings() {
  // Kept as a no-op: browsers block chrome:// navigation from web pages.
  // Users are instructed in the error text to manage permissions themselves.
  console.log("[Chat] openSiteSettings() is a no-op — browser blocks chrome:// navigation from pages.");
}

function showCallError(title, desc) {
  const { modal, error, errorTitle, errorDesc, videos, status, endBtn } = callElsWithVideos();
  if (!modal || !error) return;
  // NOTE: do NOT set call.active here — startCall()/answerCall() need
  // it to be false so a Retry can re-run getUserMedia().
  modal.hidden = false;
  modal.classList.add("is-open");
  if (videos) videos.hidden = true;
  if (status) status.hidden = true;
  if (errorTitle) errorTitle.textContent = title || "Camera/microphone access required";
  if (errorDesc) errorDesc.textContent = desc || "Allow camera/microphone in site settings, then click Retry.";
  error.hidden = false;
  error.classList.add("is-open");
  if (endBtn) endBtn.addEventListener("click", endCall);
}

function hideCallError() {
  const { error, videos, status } = callElsWithVideos();
  if (error) { error.hidden = true; error.classList.remove("is-open"); }
  if (videos) videos.hidden = false;
  if (status) status.hidden = false;
}

function callElsWithVideos() {
  const base = callEls();
  base.videos = document.querySelector(".chat-call-videos");
  return base;
}

function retryCall() {
  if (call._retry === "answer") answerCall();
  else startCall();
}

function closePeerConnection() {
  if (call.pc) {
    try { call.pc.close(); } catch (e) {}
    call.pc = null;
  }
}

function flushIceBuffer() {
  if (!call.iceReady || !call.pc) return;
  const buffered = call.iceBuffer;
  call.iceBuffer = [];
  for (const cand of buffered) {
    try {
      call.pc.addIceCandidate(cand).catch((e) => console.warn("[Chat] add buffered ice candidate failed:", e));
    } catch (e) {
      console.warn("[Chat] add buffered ice candidate failed:", e);
    }
  }
  console.log("[Chat] flushed", buffered.length, "buffered ICE candidates");
}

/* Connection-lifecycle handling shared by caller and callee.
 *
 * "disconnected" is TRANSIENT (Wi-Fi handover, brief network loss, NAT
 * rebind) — WebRTC keeps trying to recover and usually flips back to
 * "connected" within a few seconds. Tearing the call down immediately on
 * "disconnected" used to kill calls on any blip and left nothing to
 * reconnect to. Now: disconnected starts a grace window; connected (or any
 * other state) cancels it; only "failed" — or still-disconnected after the
 * window — ends the call.
 */
const DISCONNECT_GRACE_MS = 8000;
let disconnectTimer = null;

function clearDisconnectTimer() {
  if (disconnectTimer) {
    clearTimeout(disconnectTimer);
    disconnectTimer = null;
  }
}

function handleConnectionStateChange(pc) {
  updateCallStatusText();
  console.log("[ChatCall] connection state:", pc.connectionState);

  switch (pc.connectionState) {
    case "connected":
      clearDisconnectTimer();
      break;
    case "disconnected":
      if (!disconnectTimer) {
        console.log("[ChatCall] transient disconnect — grace window started");
        disconnectTimer = setTimeout(() => {
          disconnectTimer = null;
          console.log("[ChatCall] connection did not recover within", DISCONNECT_GRACE_MS, "ms — ending call");
          if (call.pc === pc) endCall();
        }, DISCONNECT_GRACE_MS);
      }
      break;
    case "failed":
      clearDisconnectTimer();
      endCall();
      break;
    default:
      // connecting / new / closed — cancel any pending teardown
      clearDisconnectTimer();
  }
}

async function endCall() {
  console.log("[Chat] endCall() callId=", call.callId);
  closePeerConnection();
  stopLocalStream();
  hideCallModal();
  hideIncomingCall();
  const callId = call.callId;
  // Reset all call state so the call button works again without reload.
  call.callId = null;
  call.incoming = false;
  call.offerFrom = null;
  call.offerSdp = null;
  call.offerType = null;
  call.remoteName = null;
  call._retry = null;
  if (callId) {
    await publishSignal("end", { call_id: callId }).catch(() => {});
  }
}

function showIncomingCall(fromUserId, fromUsername, callId, offerSdp, offerType) {
  console.log("[Chat] showIncomingCall() from=", fromUsername, "callId=", callId);
  const { incoming, fromLabel, acceptBtn, declineBtn } = callEls();
  if (!incoming) return;
  call.incoming = true;
  call.offerFrom = fromUserId;
  call.callId = callId;
  call.offerSdp = offerSdp;
  call.offerType = offerType;
  call.remoteName = fromUsername || "Someone";
  // The caller is already the remote peer from the callee's perspective.
  const preLabel = callEls().remoteLabel;
  if (preLabel) preLabel.textContent = call.remoteName;
  if (fromLabel) fromLabel.textContent = fromUsername || "Someone";
  incoming.hidden = false;
  incoming.classList.add("is-open");
  acceptBtn?.addEventListener("click", answerCall);
  declineBtn?.addEventListener("click", declineCall);

  // Ringing timeout: auto-end after 45 seconds if unanswered.
  call.ringTimer = setTimeout(() => {
    console.log("[Chat] ringing timeout — auto-ending call", callId);
    hideIncomingCall();
    endCall();
  }, 45000);
}

function hideIncomingCall() {
  console.log("[Chat] hideIncomingCall()");
  if (call.ringTimer) {
    clearTimeout(call.ringTimer);
    call.ringTimer = null;
  }
  const { incoming, acceptBtn, declineBtn } = callEls();
  if (incoming) { incoming.hidden = true; incoming.classList.remove("is-open"); }
  call.incoming = false;
  acceptBtn?.removeEventListener("click", answerCall);
  declineBtn?.removeEventListener("click", declineCall);
}

function checkMediaDeviceSupport() {
  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    return {
      ok: false,
      title: "Camera/microphone not supported",
      desc: "Your browser does not support getUserMedia. Video calls are not available.",
    };
  }
  return { ok: true };
}

function mapMediaError(e) {
  const name = e && e.name ? e.name : "";
  const msg = e && e.message ? e.message : "";

  // Chrome throws on non-secure origins (HTTP, non-localhost):
  // "Failed to execute 'getUserMedia' on 'NavigatorMediaDevices': Only secure origins are allowed."
  if (msg.includes("secure origin") || msg.includes("secure context") || msg.includes("Only secure origins")) {
    return {
      title: "Secure context required",
      desc: "Video calls need a secure context. Open this page over HTTPS or on http://localhost.",
    };
  }

  if (name === "NotAllowedError") {
    return {
      title: "Camera/microphone permission denied",
      desc: "You denied camera/microphone access. Grant it in the site settings, then click Retry.",
    };
  }
  if (name === "NotFoundError") {
    return {
      title: "No camera/microphone found",
      desc: "No camera or microphone device was detected on this device.",
    };
  }
  if (name === "NotReadableError") {
    return {
      title: "Camera/microphone in use",
      desc: "Another application is using the camera or microphone. Close it and try again.",
    };
  }
  if (name === "TypeError" && msg.includes("getUserMedia")) {
    return {
      title: "Secure context required",
      desc: "Video calls need a secure context. Open this page over HTTPS or on http://localhost.",
    };
  }
  return {
    title: "Camera/microphone access failed",
    desc: msg || "Could not access the camera or microphone.",
  };
}

async function startCall() {
  const ctx = window.CHAT_CONTEXT || {};
  const mode = ctx.callMode || "admin";
  console.log("[Chat] startCall() triggered, mode=", mode, "active=", call.active);
  // Server-renders the button only for users allowed to start calls, but
  // re-check here anyway (defense in depth; the API enforces it for real).
  if (!ctx.loggedIn || mode === "off") return;
  if (mode === "admin" && !window.ChatBridge.isAdmin()) return;
  if (call.active) return;

  const support = checkMediaDeviceSupport();
  if (!support.ok) {
    console.warn("[Chat] media device support check failed:", support.title);
    call._retry = "start";
    showCallError(support.title, support.desc);
    return;
  }

  try {
    call.localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
  } catch (e) {
    console.warn("[Chat] camera/mic access denied:", e.name, e.message);
    call._retry = "start";
    const mapped = mapMediaError(e);
    showCallError(mapped.title, mapped.desc);
    return;
  }
  console.log("[Chat] getUserMedia success, tracks=", call.localStream.getTracks().length);

  const pc = new RTCPeerConnection({ iceServers: CALL_STUN });
  call.pc = pc;

  call.localStream.getTracks().forEach((track) => pc.addTrack(track, call.localStream));

  pc.ontrack = (e) => {
    const { remoteVideo, remoteLabel } = callEls();
    if (remoteVideo && e.streams[0]) {
      remoteVideo.srcObject = e.streams[0];
      call.remoteStream = e.streams[0];
      if (remoteLabel) remoteLabel.textContent = call.remoteName || "Peer";
    }
  };

  pc.onicecandidate = (e) => {
    if (e.candidate) {
      console.log("[Chat] ICE candidate generated");
      // Buffer outgoing candidates until the offer has been published.
      if (call.iceReady) {
        publishSignal("ice", {
          call_id: call.callId,
          candidate: JSON.stringify(e.candidate),
        });
      } else {
        call.iceBuffer.push(e.candidate);
      }
    }
  };

  pc.onconnectionstatechange = () => handleConnectionStateChange(pc);

  call.callId = crypto.randomUUID?.() || Math.random().toString(36).slice(2);
  console.log("[Chat] callId=", call.callId);

  try {
    const offer = await pc.createOffer();
    await pc.setLocalDescription(offer);
    call.iceReady = true;
    console.log("[Chat] offer created:", offer.type);
    const result = await publishSignal("offer", {
      call_id: call.callId,
      sdp: offer.sdp,
      sdp_type: offer.type,
    });
    if (!result.ok) {
      console.warn("[Chat] offer signal publish failed:", result.error);
      if (result.status === 403) {
        // Admin disabled calls after this page was loaded. The toast in
        // publishSignal() already told the user — clean up quietly instead
        // of showing a scary error card, then stop the camera.
        closePeerConnection();
        stopLocalStream();
        return;
      }
      showCallError(
        "Call could not be started",
        "The call signal could not be sent to the server. Please try again."
      );
      return;
    }
    console.log("[Chat] offer signal published");
    // Flush any ICE candidates gathered before the offer was published.
    flushIceBuffer();
    const { status, localVideo } = callEls();
    if (status) status.textContent = "Waiting for someone to join…";
    if (localVideo) localVideo.srcObject = call.localStream;
    hideCallError();
    showCallModal();
  } catch (e) {
    console.warn("[Chat] call offer failed:", e);
    endCall();
  }
}

async function answerCall() {
  console.log("[Chat] answerCall() for callId=", call.callId);
  if (!call.callId || !call.offerSdp) return;

  const support = checkMediaDeviceSupport();
  if (!support.ok) {
    console.warn("[Chat] media device support check failed:", support.title);
    call._retry = "answer";
    declineCall();
    showCallError(support.title, support.desc);
    return;
  }

  try {
    call.localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
  } catch (e) {
    console.warn("[Chat] camera/mic access denied:", e.name, e.message);
    call._retry = "answer";
    declineCall();
    const mapped = mapMediaError(e);
    showCallError(mapped.title, mapped.desc);
    return;
  }

  const pc = new RTCPeerConnection({ iceServers: CALL_STUN });
  call.pc = pc;

  call.localStream.getTracks().forEach((track) => pc.addTrack(track, call.localStream));

  pc.ontrack = (e) => {
    const { remoteVideo, remoteLabel } = callEls();
    if (remoteVideo && e.streams[0]) {
      remoteVideo.srcObject = e.streams[0];
      call.remoteStream = e.streams[0];
      if (remoteLabel) remoteLabel.textContent = call.remoteName || "Peer";
    }
  };

  pc.onicecandidate = (e) => {
    if (e.candidate) {
      // Buffer outgoing candidates until the answer has been published.
      if (call.iceReady) {
        publishSignal("ice", {
          call_id: call.callId,
          candidate: JSON.stringify(e.candidate),
        });
      } else {
        call.iceBuffer.push(e.candidate);
      }
    }
  };

  pc.onconnectionstatechange = () => handleConnectionStateChange(pc);

  hideIncomingCall();

  try {
    const offer = new RTCSessionDescription({ sdp: call.offerSdp, type: call.offerType });
    await pc.setRemoteDescription(offer);
    call.iceReady = true;
    const answer = await pc.createAnswer();
    await pc.setLocalDescription(answer);
    const answerResult = await publishSignal("answer", {
      call_id: call.callId,
      sdp: answer.sdp,
      sdp_type: answer.type,
    });
    if (!answerResult.ok) {
      console.warn("[Chat] answer signal publish failed:", answerResult.error);
      if (answerResult.status === 403) {
        // Admin disabled calls mid-ring. Toast already shown — drop the
        // ringing UI and clean up without an error card.
        closePeerConnection();
        stopLocalStream();
        call.callId = null;
        call.incoming = false;
        call.offerFrom = null;
        call.offerSdp = null;
        call.offerType = null;
        call.remoteName = null;
        return;
      }
      showCallError(
        "Call could not be answered",
        "The call answer could not be sent to the server. Please try again."
      );
      return;
    }
    // Flush any ICE candidates gathered before the answer was published.
    flushIceBuffer();
    updateCallStatusText();
    const { localVideo } = callEls();
    if (localVideo) localVideo.srcObject = call.localStream;
    hideCallError();
    showCallModal();
    console.log("[Chat] answer published and modal opened");
  } catch (e) {
    console.warn("[Chat] call answer failed:", e);
    endCall();
  }
}

function declineCall() {
  console.log("[Chat] declineCall() callId=", call.callId);
  hideIncomingCall();
  if (call.callId) {
    publishSignal("end", { call_id: call.callId });
    call.callId = null;
  }
  call.incoming = false;
  call.offerFrom = null;
  call.offerSdp = null;
  call.offerType = null;
  call.remoteName = null;
}

/* ==========================================================================
 * Socket event handling — call signaling
 * ========================================================================= */

function handleCallEvent(evt) {
  const callId = evt.call_id;
  const fromId = evt.from_user_id;
  const fromName = evt.from_username || "Someone";

  // Ignore our own signals.
  const myId = window.ChatBridge.myUserId();
  if (myId !== null && fromId == myId) return;

  console.log("[Chat] handleCallEvent:", evt.type, "callId=", callId, "from=", fromName);

  if (evt.type === "call-offer") {
    if (call.active) { console.log("[Chat] call-offer ignored (already in call)"); return; }
    showIncomingCall(fromId, fromName, callId, evt.sdp, evt.sdp_type);
    return;
  }

  if (evt.type === "call-answer") {
    if (!call.active || call.callId !== callId) { console.log("[Chat] call-answer ignored"); return; }
    // The answerer's username is the remote peer's real display name —
    // learn it here so the video label shows the person, not "Peer".
    if (evt.from_username) {
      call.remoteName = evt.from_username;
      const label = callEls().remoteLabel;
      if (label) label.textContent = evt.from_username;
    }
    updateCallStatusText();
    try {
      const answer = new RTCSessionDescription({ sdp: evt.sdp, type: evt.sdp_type });
      call.pc?.setRemoteDescription(answer).catch((e) => console.warn("[Chat] set remote answer failed:", e));
    } catch (e) {
      console.warn("[Chat] invalid answer:", e);
    }
    return;
  }

  if (evt.type === "call-ice") {
    if (!call.active || call.callId !== callId) { console.log("[Chat] call-ice ignored"); return; }
    try {
      const cand = JSON.parse(evt.candidate);
      if (call.iceReady && call.pc) {
        call.pc.addIceCandidate(cand).catch((e) => console.warn("[Chat] add ice candidate failed:", e));
      } else {
        call.iceBuffer.push(cand);
        console.log("[Chat] buffered ICE candidate (iceReady=", call.iceReady, ")");
      }
    } catch (e) {
      console.warn("[Chat] invalid ice candidate:", e);
    }
    return;
  }

  if (evt.type === "call-end") {
    console.log("[Chat] call-end received for callId=", callId);
    if (!callId || call.callId === callId) endCall();
    return;
  }
}

/* Wire the call UI. Runs on DOMContentLoaded via chat.js (which calls
   window.ChatCall.init()). The call button is server-rendered only for
   viewers allowed to start calls, so a missing element simply means the
   feature is off for this user. */
function init() {
  const callToggle = document.getElementById("chat-call-toggle");
  if (callToggle) {
    callToggle.addEventListener("click", startCall);
    console.log("[ChatCall] video call toggle listener attached");
  } else {
    console.log("[ChatCall] video call toggle not rendered (feature disabled for this user)");
  }

  // Permission-error actions inside the call modal.
  const permDismiss = document.getElementById("chat-call-dismiss-error");
  const permRetry = document.getElementById("chat-call-retry-perm");
  if (permDismiss) permDismiss.addEventListener("click", endCall);
  if (permRetry) permRetry.addEventListener("click", retryCall);
}

/* Teardown for chat.js's beforeunload handler: notify the peer, drop the
   media streams and release the peer connection. */
function cleanup() {
  if (call.ringTimer) {
    clearTimeout(call.ringTimer);
    call.ringTimer = null;
  }
  if (call.callId) {
    publishSignal("end", { call_id: call.callId }).catch(() => {});
  }
  closePeerConnection();
  stopLocalStream();
}

    /* Public surface used by chat.js and nothing else. */
    window.ChatCall = {
      onSignalEvent: handleCallEvent,
      onPresence: function () { if (call.active) updateCallStatusText(); },
      isActive: function () { return call.active; },
      refreshStatus: updateCallStatusText,
      init,
      cleanup,
    };

    console.log("[ChatCall] module ready, mode=", (window.CHAT_CONTEXT || {}).callMode);
  })();
} catch (e) {
  console.error("[ChatCall] init error:", e);
}
