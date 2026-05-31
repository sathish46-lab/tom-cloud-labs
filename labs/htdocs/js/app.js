/*!
  * CoreUI v5.5.0 (https://coreui.io)
  * Copyright 2025 The CoreUI Team (https://github.com/orgs/coreui/people)
  * Licensed under MIT (https://github.com/coreui/coreui/blob/main/LICENSE)
  */
!function(t,e){"object"==typeof exports&&"undefined"!=typeof module?module.exports=e():"function"==typeof define&&define.amd?define(e):(t="undefined"!=typeof globalThis?globalThis:t||self).coreui=e()}(this,function(){"use strict";const t=new Map,e={set(e,i,n){t.has(e)||t.set(e,new Map);const s=t.get(e);s.has(i)||0===s.size?s.set(i,n):console.error(`Bootstrap doesn't allow more than one instance per element. Bound instance: ${Array.from(s.keys())[0]}.`)},get:(e,i)=>t.has(e)&&t.get(e).get(i)||null,remove(e,i){if(!t.has(e))return;const n=t.get(e);n.delete(i),0===n.size&&t.delete(e)}},i="transitionend",n=t=>(t&&window.CSS&&window.CSS.escape&&(t=t.replace(/#([^\s"#']+)/g,(t,e)=>`#${CSS.escape(e)}`)),t),s=t=>null==t?`${t}`:Object.prototype.toString.call(t).match(/\s([a-z]+)/i)[1].toLowerCase(),o=t=>{t.dispatchEvent(new Event(i))},r=t=>!(!t||"object"!=typeof t)&&(void 0!==t.jquery&&(t=t[0]),void 0!==t.nodeType),a=t=>r(t)?t.jquery?t[0]:t:"string"==typeof t&&t.length>0?document.querySelector(n(t)):null,l=t=>{if(!r(t)||0===t.getClientRects().length)return!1;const e="visible"===getComputedStyle(t).getPropertyValue("visibility"),i=t.closest("details:not([open])");if(!i)return e;if(i!==t){const e=t.closest("summary");if(e&&e.parentNode!==i)return!1;if(null===e)return!1}return e},c=t=>!t||t.nodeType!==Node.ELEMENT_NODE||!!t.classList.contains("disabled")||(void 0!==t.disabled?t.disabled:t.hasAttribute("disabled")&&"false"!==t.getAttribute("disabled")),h=t=>{if(!document.documentElement.attachShadow)return null;if("function"==typeof t.getRootNode){const e=t.getRootNode();return e instanceof ShadowRoot?e:null}return t instanceof ShadowRoot?t:t.parentNode?h(t.parentNode):null},d=()=>{},u=t=>{t.offsetHeight},f=()=>window.jQuery&&!document.body.hasAttribute("data-coreui-no-jquery")?window.jQuery:null,p=[],g=()=>"rtl"===document.documentElement.dir,m=t=>{var e;e=()=>{const e=f();if(e){const i=t.NAME,n=e.fn[i];e.fn[i]=t.jQueryInterface,e.fn[i].Constructor=t,e.fn[i].noConflict=()=>(e.fn[i]=n,t.jQueryInterface)}},"loading"===document.readyState?(p.length||document.addEventListener("DOMContentLoaded",()=>{for(const t of p)t()}),p.push(e)):e()},_=(t,e=[],i=t)=>"function"==typeof t?t.call(...e):i,b=(t,e,n=!0)=>{if(!n)return void _(t);const s=(t=>{if(!t)return 0;let{transitionDuration:e,transitionDelay:i}=window.getComputedStyle(t);const n=Number.parseFloat(e),s=Number.parseFloat(i);return n||s?(e=e.split(",")[0],i=i.split(",")[0],1e3*(Number.parseFloat(e)+Number.parseFloat(i))):0})(e)+5;let r=!1;const a=({target:n})=>{n===e&&(r=!0,e.removeEventListener(i,a),_(t))};e.addEventListener(i,a),setTimeout(()=>{r||o(e)},s)},v=(t,e,i,n)=>{const s=t.length;let o=t.indexOf(e);return-1===o?!i&&n?t[s-1]:t[0]:(o+=i?1:-1,n&&(o=(o+s)%s),t[Math.max(0,Math.min(o,s-1))])},y=/[^.]*(?=\..*)\.|.*/,w=/\..*/,A=/::\d+$/,E={};let C=1;const T={mouseenter:"mouseover",mouseleave:"mouseout"},O=new Set(["click","dblclick","mouseup","mousedown","contextmenu","mousewheel","DOMMouseScroll","mouseover","mouseout","mousemove","selectstart","selectend","keydown","keypress","keyup","orientationchange","touchstart","touchmove","touchend","touchcancel","pointerdown","pointermove","pointerup","pointerleave","pointercancel","gesturestart","gesturechange","gestureend","focus","blur","change","reset","select","submit","focusin","focusout","load","unload","beforeunload","resize","move","DOMContentLoaded","readystatechange","error","abort","scroll"]);function k(t,e){return e&&`${e}::${C++}`||t.uidEvent||C++}function x(t){const e=k(t);return t.uidEvent=e,E[e]=E[e]||{},E[e]}function L(t,e,i=null){return Object.values(t).find(t=>t.callable===e&&t.delegationSelector===i)}function S(t,e,i){const n="string"==typeof e,s=n?i:e||i;let o=I(t);return O.has(o)||(o=t),[n,s,o]}function D(t,e,i,n,s){if("string"!=typeof e||!t)return;let[o,r,a]=S(e,i,n);if(e in T){const t=t=>function(e){if(!e.relatedTarget||e.relatedTarget!==e.delegateTarget&&!e.delegateTarget.contains(e.relatedTarget))return t.call(this,e)};r=t(r)}const l=x(t),c=l[a]||(l[a]={}),h=L(c,r,o?i:null);if(h)return void(h.oneOff=h.oneOff&&s);const d=k(r,e.replace(y,"")),u=o?function(t,e,i){return function n(s){const o=t.querySelectorAll(e);for(let{target:r}=s;r&&r!==this;r=r.parentNode)for(const a of o)if(a===r)return P(s,{delegateTarget:r}),n.oneOff&&M.off(t,s.type,e,i),i.apply(r,[s])}}(t,i,r):function(t,e){return function i(n){return P(n,{delegateTarget:t}),i.oneOff&&M.off(t,n.type,e),e.apply(t,[n])}}(t,r);u.delegationSelector=o?i:null,u.callable=r,u.oneOff=s,u.uidEvent=d,c[d]=u,t.addEventListener(a,u,o)}function $(t,e,i,n,s){const o=L(e[i],n,s);o&&(t.removeEventListener(i,o,Boolean(s)),delete e[i][o.uidEvent])}function N(t,e,i,n){const s=e[i]||{};for(const[o,r]of Object.entries(s))o.includes(n)&&$(t,e,i,r.callable,r.delegationSelector)}function I(t){return t=t.replace(w,""),T[t]||t}const M={on(t,e,i,n){D(t,e,i,n,!1)},one(t,e,i,n){D(t,e,i,n,!0)},off(t,e,i,n){if("string"!=typeof e||!t)return;const[s,o,r]=S(e,i,n),a=r!==e,l=x(t),c=l[r]||{},h=e.startsWith(".");if(void 0===o){if(h)for(const i of Object.keys(l))N(t,l,i,e.slice(1));for(const[i,n]of Object.entries(c)){const s=i.replace(A,"");a&&!e.includes(s)||$(t,l,r,n.callable,n.delegationSelector)}}else{if(!Object.keys(c).length)return;$(t,l,r,o,s?i:null)}},trigger(t,e,i){if("string"!=typeof e||!t)return null;const n=f();let s=null,o=!0,r=!0,a=!1;e!==I(e)&&n&&(s=n.Event(e,i),n(t).trigger(s),o=!s.isPropagationStopped(),r=!s.isImmediatePropagationStopped(),a=s.isDefaultPrevented());const l=P(new Event(e,{bubbles:o,cancelable:!0}),i);return a&&l.preventDefault(),r&&t.dispatchEvent(l),l.defaultPrevented&&s&&s.preventDefault(),l}};function P(t,e={}){for(const[i,n]of Object.entries(e))try{t[i]=n}catch(e){Object.defineProperty(t,i,{configurable:!0,get:()=>n})}return t}function j(t){if("true"===t)return!0;if("false"===t)return!1;if(t===Number(t).toString())return Number(t);if(""===t||"null"===t)return null;if("string"!=typeof t)return t;try{return JSON.parse(decodeURIComponent(t))}catch(e){return t}}function F(t){return t.replace(/[A-Z]/g,t=>`-${t.toLowerCase()}`)}const H={setDataAttribute(t,e,i){t.setAttribute(`data-coreui-${F(e)}`,i)},removeDataAttribute(t,e){t.removeAttribute(`data-coreui-${F(e)}`)},getDataAttributes(t){if(!t)return{};const e={},i=Object.keys(t.dataset).filter(t=>t.startsWith("coreui")&&!t.startsWith("coreuiConfig"));for(const n of i){let i=n.replace(/^coreui/,"");i=i.charAt(0).toLowerCase()+i.slice(1),e[i]=j(t.dataset[n])}return e},getDataAttribute:(t,e)=>j(t.getAttribute(`data-coreui-${F(e)}`))};class B{static get Default(){return{}}static get DefaultType(){return{}}static get NAME(){throw new Error('You have to implement the static method "NAME", for each component!')}_getConfig(t){return t=this._mergeConfigObj(t),t=this._configAfterMerge(t),this._typeCheckConfig(t),t}_configAfterMerge(t){return t}_mergeConfigObj(t,e){const i=r(e)?H.getDataAttribute(e,"config"):{};return{...this.constructor.Default,..."object"==typeof i?i:{},...r(e)?H.getDataAttributes(e):{},..."object"==typeof t?t:{}}}_typeCheckConfig(t,e=this.constructor.DefaultType){for(const[i,n]of Object.entries(e)){const e=t[i],o=r(e)?"element":s(e);if(!new RegExp(n).test(o))throw new TypeError(`${this.constructor.NAME.toUpperCase()}: Option "${i}" provided type "${o}" but expected type "${n}".`)}}}class W extends B{constructor(t,i){super(),(t=a(t))&&(this._element=t,this._config=this._getConfig(i),e.set(this._element,this.constructor.DATA_KEY,this))}dispose(){e.remove(this._element,this.constructor.DATA_KEY),M.off(this._element,this.constructor.EVENT_KEY);for(const t of Object.getOwnPropertyNames(this))this[t]=null}_queueCallback(t,e,i=!0){b(t,e,i)}_getConfig(t){return t=this._mergeConfigObj(t,this._element),t=this._configAfterMerge(t),this._typeCheckConfig(t),t}static getInstance(t){return e.get(a(t),this.DATA_KEY)}static getOrCreateInstance(t,e={}){return this.getInstance(t)||new this(t,"object"==typeof e?e:null)}static get VERSION(){return"5.5.0"}static get DATA_KEY(){return`coreui.${this.NAME}`}static get EVENT_KEY(){return`.${this.DATA_KEY}`}static eventName(t){return`${t}${this.EVENT_KEY}`}}const z=t=>{let e=t.getAttribute("data-coreui-target");if(!e||"#"===e){let i=t.getAttribute("href");if(!i||!i.includes("#")&&!i.startsWith("."))return null;i.includes("#")&&!i.startsWith("#")&&(i=`#${i.split("#")[1]}`),e=i&&"#"!==i?i.trim():null}return e?e.split(",").map(t=>n(t)).join(","):null},q={find:(t,e=document.documentElement)=>[].concat(...Element.prototype.querySelectorAll.call(e,t)),findOne:(t,e=document.documentElement)=>Element.prototype.querySelector.call(e,t),children:(t,e)=>[].concat(...t.children).filter(t=>t.matches(e)),parents(t,e){const i=[];let n=t.parentNode.closest(e);for(;n;)i.push(n),n=n.parentNode.closest(e);return i},prev(t,e){let i=t.previousElementSibling;for(;i;){if(i.matches(e))return[i];i=i.previousElementSibling}return[]},next(t,e){let i=t.nextElementSibling;for(;i;){if(i.matches(e))return[i];i=i.nextElementSibling}return[]},focusableChildren(t){const e=["a","button","input","textarea","select","details","[tabindex]",'[contenteditable="true"]'].map(t=>`${t}:not([tabindex^="-"])`).join(",");return this.find(e,t).filter(t=>!c(t)&&l(t))},getSelectorFromElement(t){const e=z(t);return e&&q.findOne(e)?e:null},getElementFromSelector(t){const e=z(t);return e?q.findOne(e):null},getMultipleElementsFromSelector(t){const e=z(t);return e?q.find(e):[]}},R=(t,e="hide")=>{const i=`click.dismiss${t.EVENT_KEY}`,n=t.NAME;M.on(document,i,`[data-coreui-dismiss="${n}"]`,function(i){if(["A","AREA"].includes(this.tagName)&&i.preventDefault(),c(this))return;const s=q.getElementFromSelector(this)||this.closest(`.${n}`);t.getOrCreateInstance(s)[e]()})},V=".coreui.alert",U=`close${V}`,K=`closed${V}`;class Q extends W{static get NAME(){return"alert"}close(){if(M.trigger(this._element,U).defaultPrevented)return;this._element.classList.remove("show");const t=this._element.classList.contains("fade");this._queueCallback(()=>this._destroyElement(),this._element,t)}_destroyElement(){this._element.remove(),M.trigger(this._element,K),this.dispose()}static jQueryInterface(t){return this.each(function(){const e=Q.getOrCreateInstance(this);if("string"==typeof t){if(void 0===e[t]||t.startsWith("_")||"constructor"===t)throw new TypeError(`No method named "${t}"`);e[t](this)}})}}R(Q,"close"),m(Q);const Y='[data-coreui-toggle="button"]';class X extends W{static get NAME(){return"button"}toggle(){this._element.setAttribute("aria-pressed",this._element.classList.toggle("active"))}static jQueryInterface(t){return this.each(function(){const e=X.getOrCreateInstance(this);"toggle"===t&&e[t]()})}}M.on(document,"click.coreui.button.data-api",Y,t=>{t.preventDefault();const e=t.target.closest(Y);X.getOrCreateInstance(e).toggle()}),m(X);const G=".coreui.swipe",J=`touchstart${G}`,Z=`touchmove${G}`,tt=`touchend${G}`,et=`pointerdown${G}`,it=`pointerup${G}`,nt={endCallback:null,leftCallback:null,rightCallback:null},st={endCallback:"(function|null)",leftCallback:"(function|null)",rightCallback:"(function|null)"};class ot extends B{constructor(t,e){super(),this._element=t,t&&ot.isSupported()&&(this._config=this._getConfig(e),this._deltaX=0,this._supportPointerEvents=Boolean(window.PointerEvent),this._initEvents())}static get Default(){return nt}static get DefaultType(){return st}static get NAME(){return"swipe"}dispose(){M.off(this._element,G)}_start(t){this._supportPointerEvents?this._eventIsPointerPenTouch(t)&&(this._deltaX=t.clientX):this._deltaX=t.touches[0].clientX}_end(t){this._eventIsPointerPenTouch(t)&&(this._deltaX=t.clientX-this._deltaX),this._handleSwipe(),_(this._config.endCallback)}_move(t){this._deltaX=t.touches&&t.touches.length>1?0:t.touches[0].clientX-this._deltaX}_handleSwipe(){const t=Math.abs(this._deltaX);if(t<=40)return;const e=t/this._deltaX;this._deltaX=0,e&&_(e>0?this._config.rightCallback:this._config.leftCallback)}_initEvents(){this._supportPointerEvents?(M.on(this._element,et,t=>this._start(t)),M.on(this._element,it,t=>this._end(t)),this._element.classList.add("pointer-event")):(M.on(this._element,J,t=>this._start(t)),M.on(this._element,Z,t=>this._move(t)),M.on(this._element,tt,t=>this._end(t)))}_eventIsPointerPenTouch(t){return this._supportPointerEvents&&("pen"===t.pointerType||"touch"===t.pointerType)}static isSupported(){return"ontouchstart"in document.documentElement||navigator.maxTouchPoints>0}}const rt=".coreui.carousel",at=".data-api",lt="ArrowLeft",ct="ArrowRight",ht="next",dt="prev",ut="left",ft="right",pt=`slide${rt}`,gt=`slid${rt}`,mt=`keydown${rt}`,_t=`mouseenter${rt}`,bt=`mouseleave${rt}`,vt=`dragstart${rt}`,yt=`load${rt}${at}`,wt=`click${rt}${at}`,At="carousel",Et="active",Ct=".active",Tt=".carousel-item",Ot=Ct+Tt,kt={[lt]:ft,[ct]:ut},xt={interval:5e3,keyboard:!0,pause:"hover",ride:!1,touch:!0,wrap:!0},Lt={interval:"(number|boolean)",keyboard:"boolean",pause:"(string|boolean)",ride:"(boolean|string)",touch:"boolean",wrap:"boolean"};class St extends W{constructor(t,e){super(t,e),this._interval=null,this._activeElement=null,this._isSliding=!1,this.touchTimeout=null,this._swipeHelper=null,this._indicatorsElement=q.findOne(".carousel-indicators",this._element),this._addEventListeners(),this._config.ride===At&&this.cycle()}static get Default(){return xt}static get DefaultType(){return Lt}static get NAME(){return"carousel"}next(){this._slide(ht)}nextWhenVisible(){!document.hidden&&l(this._element)&&this.next()}prev(){this._slide(dt)}pause(){this._isSliding&&o(this._element),this._clearInterval()}cycle(){this._clearInterval(),this._updateInterval(),this._interval=setInterval(()=>this.nextWhenVisible(),this._config.interval)}_maybeEnableCycle(){this._config.ride&&(this._isSliding?M.one(this._element,gt,()=>this.cycle()):this.cycle())}to(t){const e=this._getItems();if(t>e.length-1||t<0)return;if(this._isSliding)return void M.one(this._element,gt,()=>this.to(t));const i=this._getItemIndex(this._getActive());if(i===t)return;const n=t>i?ht:dt;this._slide(n,e[t])}dispose(){this._swipeHelper&&this._swipeHelper.dispose(),super.dispose()}_configAfterMerge(t){return t.defaultInterval=t.interval,t}_addEventListeners(){this._config.keyboard&&M.on(this._element,mt,t=>this._keydown(t)),"hover"===this._config.pause&&(M.on(this._element,_t,()=>this.pause()),M.on(this._element,bt,()=>this._maybeEnableCycle())),this._config.touch&&ot.isSupported()&&this._addTouchEventListeners()}_addTouchEventListeners(){for(const t of q.find(".carousel-item img",this._element))M.on(t,vt,t=>t.preventDefault());const t={leftCallback:()=>this._slide(this._directionToOrder(ut)),rightCallback:()=>this._slide(this._directionToOrder(ft)),endCallback:()=>{"hover"===this._config.pause&&(this.pause(),this.touchTimeout&&clearTimeout(this.touchTimeout),this.touchTimeout=setTimeout(()=>this._maybeEnableCycle(),500+this._config.interval))}};this._swipeHelper=new ot(this._element,t)}_keydown(t){if(/input|textarea/i.test(t.target.tagName))return;const e=kt[t.key];e&&(t.preventDefault(),this._slide(this._directionToOrder(e)))}_getItemIndex(t){return this._getItems().indexOf(t)}_setActiveIndicatorElement(t){if(!this._indicatorsElement)return;const e=q.findOne(Ct,this._indicatorsElement);e.classList.remove(Et),e.removeAttribute("aria-current");const i=q.findOne(`[data-coreui-slide-to="${t}"]`,this._indicatorsElement);i&&(i.classList.add(Et),i.setAttribute("aria-current","true"))}_updateInterval(){const t=this._activeElement||this._getActive();if(!t)return;const e=Number.parseInt(t.getAttribute("data-coreui-interval"),10);this._config.interval=e||this._config.defaultInterval}_slide(t,e=null){if(this._isSliding)return;const i=this._getActive(),n=t===ht,s=e||v(this._getItems(),i,n,this._config.wrap);if(s===i)return;const o=this._getItemIndex(s),r=e=>M.trigger(this._element,e,{relatedTarget:s,direction:this._orderToDirection(t),from:this._getItemIndex(i),to:o});if(r(pt).defaultPrevented)return;if(!i||!s)return;const a=Boolean(this._interval);this.pause(),this._isSliding=!0,this._setActiveIndicatorElement(o),this._activeElement=s;const l=n?"carousel-item-start":"carousel-item-end",c=n?"carousel-item-next":"carousel-item-prev";s.classList.add(c),u(s),i.classList.add(l),s.classList.add(l),this._queueCallback(()=>{s.classList.remove(l,c),s.classList.add(Et),i.classList.remove(Et,c,l),this._isSliding=!1,r(gt)},i,this._isAnimated()),a&&this.cycle()}_isAnimated(){return this._element.classList.contains("slide")}_getActive(){return q.findOne(Ot,this._element)}_getItems(){return q.find(Tt,this._element)}_clearInterval(){this._interval&&(clearInterval(this._interval),this._interval=null)}_directionToOrder(t){return g()?t===ut?dt:ht:t===ut?ht:dt}_orderToDirection(t){return g()?t===dt?ut:ft:t===dt?ft:ut}static jQueryInterface(t){return this.each(function(){const e=St.getOrCreateInstance(this,t);if("number"!=typeof t){if("string"==typeof t){if(void 0===e[t]||t.startsWith("_")||"constructor"===t)throw new TypeError(`No method named "${t}"`);e[t]()}}else e.to(t)})}}M.on(document,wt,"[data-coreui-slide], [data-coreui-slide-to]",function(t){const e=q.getElementFromSelector(this);if(!e||!e.classList.contains(At))return;t.preventDefault();const i=St.getOrCreateInstance(e),n=this.getAttribute("data-coreui-slide-to");return n?(i.to(n),void i._maybeEnableCycle()):"next"===H.getDataAttribute(this,"slide")?(i.next(),void i._maybeEnableCycle()):(i.prev(),void i._maybeEnableCycle())}),M.on(window,yt,()=>{const t=q.find('[data-coreui-ride="carousel"]');for(const e of t)St.getOrCreateInstance(e)}),m(St);const Dt=".coreui.collapse",$t=`show${Dt}`,Nt=`shown${Dt}`,It=`hide${Dt}`,Mt=`hidden${Dt}`,Pt=`click${Dt}.data-api`,jt="show",Ft="collapse",Ht="collapsing",Bt=`:scope .${Ft} .${Ft}`,Wt='[data-coreui-toggle="collapse"]',zt={parent:null,toggle:!0},qt={parent:"(null|element)",toggle:"boolean"};class Rt extends W{constructor(t,e){super(t,e),this._isTransitioning=!1,this._triggerArray=[];const i=q.find(Wt);for(const t of i){const e=q.getSelectorFromElement(t),i=q.find(e).filter(t=>t===this._element);null!==e&&i.length&&this._triggerArray.push(t)}this._initializeChildren(),this._config.parent||this._addAriaAndCollapsedClass(this._triggerArray,this._isShown()),this._config.toggle&&this.toggle()}static get Default(){return zt}static get DefaultType(){return qt}static get NAME(){return"collapse"}toggle(){this._isShown()?this.hide():this.show()}show(){if(this._isTransitioning||this._isShown())return;let t=[];if(this._config.parent&&(t=this._getFirstLevelChildren(".collapse.show, .collapse.collapsing").filter(t=>t!==this._element).map(t=>Rt.getOrCreateInstance(t,{toggle:!1}))),t.length&&t[0]._isTransitioning)return;if(M.trigger(this._element,$t).defaultPrevented)return;for(const e of t)e.hide();const e=this._getDimension();this._element.classList.remove(Ft),this._element.classList.add(Ht),this._element.style[e]=0,this._addAriaAndCollapsedClass(this._triggerArray,!0),this._isTransitioning=!0;const i=`scroll${e[0].toUpperCase()+e.slice(1)}`;this._queueCallback(()=>{this._isTransitioning=!1,this._element.classList.remove(Ht),this._element.classList.add(Ft,jt),this._element.style[e]="",M.trigger(this._element,Nt)},this._element,!0),this._element.style[e]=`${this._element[i]}px`}hide(){if(this._isTransitioning||!this._isShown())return;if(M.trigger(this._element,It).defaultPrevented)return;const t=this._getDimension();this._element.style[t]=`${this._element.getBoundingClientRect()[t]}px`,u(this._element),this._element.classList.add(Ht),this._element.classList.remove(Ft,jt);for(const t of this._triggerArray){const e=q.getElementFromSelector(t);e&&!this._isShown(e)&&this._addAriaAndCollapsedClass([t],!1)}this._isTransitioning=!0,this._element.style[t]="",this._queueCallback(()=>{this._isTransitioning=!1,this._element.classList.remove(Ht),this._element.classList.add(Ft),M.trigger(this._element,Mt)},this._element,!0)}_isShown(t=this._element){return t.classList.contains(jt)}_configAfterMerge(t){return t.toggle=Boolean(t.toggle),t.parent=a(t.parent),t}_getDimension(){return this._element.classList.contains("collapse-horizontal")?"width":"height"}_initializeChildren(){if(!this._config.parent)return;const t=this._getFirstLevelChildren(Wt);for(const e of t){const t=q.getElementFromSelector(e);t&&this._addAriaAndCollapsedClass([e],this._isShown(t))}}_getFirstLevelChildren(t){const e=q.find(Bt,this._config.parent);return q.find(t,this._config.parent).filter(t=>!e.includes(t))}_addAriaAndCollapsedClass(t,e){if(t.length)for(const i of t)i.classList.toggle("collapsed",!e),i.setAttribute("aria-expanded",e)}static jQueryInterface(t){const e={};return"string"==typeof t&&/show|hide/.test(t)&&(e.toggle=!1),this.each(function(){const i=Rt.getOrCreateInstance(this,e);if("string"==typeof t){if(void 0===i[t])throw new TypeError(`No method named "${t}"`);i[t]()}})}}M.on(document,Pt,Wt,function(t){("A"===t.target.tagName||t.delegateTarget&&"A"===t.delegateTarget.tagName)&&t.preventDefault();for(const t of q.getMultipleElementsFromSelector(this))Rt.getOrCreateInstance(t,{toggle:!1}).toggle()}),m(Rt);var Vt="top",Ut="bottom",Kt="right",Qt="left",Yt="auto",Xt=[Vt,Ut,Kt,Qt],Gt="start",Jt="end",Zt="clippingParents",te="viewport",ee="popper",ie="reference",ne=Xt.reduce(function(t,e){return t.concat([e+"-"+Gt,e+"-"+Jt])},[]),se=[].concat(Xt,[Yt]).reduce(function(t,e){return t.concat([e,e+"-"+Gt,e+"-"+Jt])},[]),oe="beforeRead",re="read",ae="afterRead",le="beforeMain",ce="main",he="afterMain",de="beforeWrite",ue="write",fe="afterWrite",pe=[oe,re,ae,le,ce,he,de,ue,fe];function ge(t){return t?(t.nodeName||"").toLowerCase():null}function me(t){if(null==t)return window;if("[object Window]"!==t.toString()){var e=t.ownerDocument;return e&&e.defaultView||window}return t}function _e(t){return t instanceof me(t).Element||t instanceof Element}function be(t){return t instanceof me(t).HTMLElement||t instanceof HTMLElement}function ve(t){return"undefined"!=typeof ShadowRoot&&(t instanceof me(t).ShadowRoot||t instanceof ShadowRoot)}const ye={name:"applyStyles",enabled:!0,phase:"write",fn:function(t){var e=t.state;Object.keys(e.elements).forEach(function(t){var i=e.styles[t]||{},n=e.attributes[t]||{},s=e.elements[t];be(s)&&ge(s)&&(Object.assign(s.style,i),Object.keys(n).forEach(function(t){var e=n[t];!1===e?s.removeAttribute(t):s.setAttribute(t,!0===e?"":e)}))})},effect:function(t){var e=t.state,i={popper:{position:e.options.strategy,left:"0",top:"0",margin:"0"},arrow:{position:"absolute"},reference:{}};return Object.assign(e.elements.popper.style,i.popper),e.styles=i,e.elements.arrow&&Object.assign(e.elements.arrow.style,i.arrow),function(){Object.keys(e.elements).forEach(function(t){var n=e.elements[t],s=e.attributes[t]||{},o=Object.keys(e.styles.hasOwnProperty(t)?e.styles[t]:i[t]).reduce(function(t,e){return t[e]="",t},{});be(n)&&ge(n)&&(Object.assign(n.style,o),Object.keys(s).forEach(function(t){n.removeAttribute(t)}))})}},requires:["computeStyles"]};function we(t){return t.split("-")[0]}var Ae=Math.max,Ee=Math.min,Ce=Math.round;function Te(){var t=navigator.userAgentData;return null!=t&&t.brands&&Array.isArray(t.brands)?t.brands.map(function(t){return t.brand+"/"+t.version}).join(" "):navigator.userAgent}function Oe(){return!/^((?!chrome|android).)*safari/i.test(Te())}function ke(t,e,i){void 0===e&&(e=!1),void 0===i&&(i=!1);var n=t.getBoundingClientRect(),s=1,o=1;e&&be(t)&&(s=t.offsetWidth>0&&Ce(n.width)/t.offsetWidth||1,o=t.offsetHeight>0&&Ce(n.height)/t.offsetHeight||1);var r=(_e(t)?me(t):window).visualViewport,a=!Oe()&&i,l=(n.left+(a&&r?r.offsetLeft:0))/s,c=(n.top+(a&&r?r.offsetTop:0))/o,h=n.width/s,d=n.height/o;return{width:h,height:d,top:c,right:l+h,bottom:c+d,left:l,x:l,y:c}}function xe(t){var e=ke(t),i=t.offsetWidth,n=t.offsetHeight;return Math.abs(e.width-i)<=1&&(i=e.width),Math.abs(e.height-n)<=1&&(n=e.height),{x:t.offsetLeft,y:t.offsetTop,width:i,height:n}}function Le(t,e){var i=e.getRootNode&&e.getRootNode();if(t.contains(e))return!0;if(i&&ve(i)){var n=e;do{if(n&&t.isSameNode(n))return!0;n=n.parentNode||n.host}while(n)}return!1}function Se(t){return me(t).getComputedStyle(t)}function De(t){return["table","td","th"].indexOf(ge(t))>=0}function $e(t){return((_e(t)?t.ownerDocument:t.document)||window.document).documentElement}function Ne(t){return"html"===ge(t)?t:t.assignedSlot||t.parentNode||(ve(t)?t.host:null)||$e(t)}function Ie(t){return be(t)&&"fixed"!==Se(t).position?t.offsetParent:null}function Me(t){for(var e=me(t),i=Ie(t);i&&De(i)&&"static"===Se(i).position;)i=Ie(i);return i&&("html"===ge(i)||"body"===ge(i)&&"static"===Se(i).position)?e:i||function(t){var e=/firefox/i.test(Te());if(/Trident/i.test(Te())&&be(t)&&"fixed"===Se(t).position)return null;var i=Ne(t);for(ve(i)&&(i=i.host);be(i)&&["html","body"].indexOf(ge(i))<0;){var n=Se(i);if("none"!==n.transform||"none"!==n.perspective||"paint"===n.contain||-1!==["transform","perspective"].indexOf(n.willChange)||e&&"filter"===n.willChange||e&&n.filter&&"none"!==n.filter)return i;i=i.parentNode}return null}(t)||e}function Pe(t){return["top","bottom"].indexOf(t)>=0?"x":"y"}function je(t,e,i){return Ae(t,Ee(e,i))}function Fe(t){return Object.assign({},{top:0,right:0,bottom:0,left:0},t)}function He(t,e){return e.reduce(function(e,i){return e[i]=t,e},{})}const Be={name:"arrow",enabled:!0,phase:"main",fn:function(t){var e,i=t.state,n=t.name,s=t.options,o=i.elements.arrow,r=i.modifiersData.popperOffsets,a=we(i.placement),l=Pe(a),c=[Qt,Kt].indexOf(a)>=0?"height":"width";if(o&&r){var h=function(t,e){return Fe("number"!=typeof(t="function"==typeof t?t(Object.assign({},e.rects,{placement:e.placement})):t)?t:He(t,Xt))}(s.padding,i),d=xe(o),u="y"===l?Vt:Qt,f="y"===l?Ut:Kt,p=i.rects.reference[c]+i.rects.reference[l]-r[l]-i.rects.popper[c],g=r[l]-i.rects.reference[l],m=Me(o),_=m?"y"===l?m.clientHeight||0:m.clientWidth||0:0,b=p/2-g/2,v=h[u],y=_-d[c]-h[f],w=_/2-d[c]/2+b,A=je(v,w,y),E=l;i.modifiersData[n]=((e={})[E]=A,e.centerOffset=A-w,e)}},effect:function(t){var e=t.state,i=t.options.element,n=void 0===i?"[data-popper-arrow]":i;null!=n&&("string"!=typeof n||(n=e.elements.popper.querySelector(n)))&&Le(e.elements.popper,n)&&(e.elements.arrow=n)},requires:["popperOffsets"],requiresIfExists:["preventOverflow"]};function We(t){return t.split("-")[1]}var ze={top:"auto",right:"auto",bottom:"auto",left:"auto"};function qe(t){var e,i=t.popper,n=t.popperRect,s=t.placement,o=t.variation,r=t.offsets,a=t.position,l=t.gpuAcceleration,c=t.adaptive,h=t.roundOffsets,d=t.isFixed,u=r.x,f=void 0===u?0:u,p=r.y,g=void 0===p?0:p,m="function"==typeof h?h({x:f,y:g}):{x:f,y:g};f=m.x,g=m.y;var _=r.hasOwnProperty("x"),b=r.hasOwnProperty("y"),v=Qt,y=Vt,w=window;if(c){var A=Me(i),E="clientHeight",C="clientWidth";A===me(i)&&"static"!==Se(A=$e(i)).position&&"absolute"===a&&(E="scrollHeight",C="scrollWidth"),(s===Vt||(s===Qt||s===Kt)&&o===Jt)&&(y=Ut,g-=(d&&A===w&&w.visualViewport?w.visualViewport.height:A[E])-n.height,g*=l?1:-1),s!==Qt&&(s!==Vt&&s!==Ut||o!==Jt)||(v=Kt,f-=(d&&A===w&&w.visualViewport?w.visualViewport.width:A[C])-n.width,f*=l?1:-1)}var T,O=Object.assign({position:a},c&&ze),k=!0===h?function(t,e){var i=t.x,n=t.y,s=e.devicePixelRatio||1;return{x:Ce(i*s)/s||0,y:Ce(n*s)/s||0}}({x:f,y:g},me(i)):{x:f,y:g};return f=k.x,g=k.y,l?Object.assign({},O,((T={})[y]=b?"0":"",T[v]=_?"0":"",T.transform=(w.devicePixelRatio||1)<=1?"translate("+f+"px, "+g+"px)":"translate3d("+f+"px, "+g+"px, 0)",T)):Object.assign({},O,((e={})[y]=b?g+"px":"",e[v]=_?f+"px":"",e.transform="",e))}const Re={name:"computeStyles",enabled:!0,phase:"beforeWrite",fn:function(t){var e=t.state,i=t.options,n=i.gpuAcceleration,s=void 0===n||n,o=i.adaptive,r=void 0===o||o,a=i.roundOffsets,l=void 0===a||a,c={placement:we(e.placement),variation:We(e.placement),popper:e.elements.popper,popperRect:e.rects.popper,gpuAcceleration:s,isFixed:"fixed"===e.options.strategy};null!=e.modifiersData.popperOffsets&&(e.styles.popper=Object.assign({},e.styles.popper,qe(Object.assign({},c,{offsets:e.modifiersData.popperOffsets,position:e.options.strategy,adaptive:r,roundOffsets:l})))),null!=e.modifiersData.arrow&&(e.styles.arrow=Object.assign({},e.styles.arrow,qe(Object.assign({},c,{offsets:e.modifiersData.arrow,position:"absolute",adaptive:!1,roundOffsets:l})))),e.attributes.popper=Object.assign({},e.attributes.popper,{"data-popper-placement":e.placement})},data:{}};var Ve={passive:!0};const Ue={name:"eventListeners",enabled:!0,phase:"write",fn:function(){},effect:function(t){var e=t.state,i=t.instance,n=t.options,s=n.scroll,o=void 0===s||s,r=n.resize,a=void 0===r||r,l=me(e.elements.popper),c=[].concat(e.scrollParents.reference,e.scrollParents.popper);return o&&c.forEach(function(t){t.addEventListener("scroll",i.update,Ve)}),a&&l.addEventListener("resize",i.update,Ve),function(){o&&c.forEach(function(t){t.removeEventListener("scroll",i.update,Ve)}),a&&l.removeEventListener("resize",i.update,Ve)}},data:{}};var Ke={left:"right",right:"left",bottom:"top",top:"bottom"};function Qe(t){return t.replace(/left|right|bottom|top/g,function(t){return Ke[t]})}var Ye={start:"end",end:"start"};function Xe(t){return t.replace(/start|end/g,function(t){return Ye[t]})}function Ge(t){var e=me(t);return{scrollLeft:e.pageXOffset,scrollTop:e.pageYOffset}}function Je(t){return ke($e(t)).left+Ge(t).scrollLeft}function Ze(t){var e=Se(t),i=e.overflow,n=e.overflowX,s=e.overflowY;return/auto|scroll|overlay|hidden/.test(i+s+n)}function ti(t){return["html","body","#document"].indexOf(ge(t))>=0?t.ownerDocument.body:be(t)&&Ze(t)?t:ti(Ne(t))}function ei(t,e){var i;void 0===e&&(e=[]);var n=ti(t),s=n===(null==(i=t.ownerDocument)?void 0:i.body),o=me(n),r=s?[o].concat(o.visualViewport||[],Ze(n)?n:[]):n,a=e.concat(r);return s?a:a.concat(ei(Ne(r)))}function ii(t){return Object.assign({},t,{left:t.x,top:t.y,right:t.x+t.width,bottom:t.y+t.height})}function ni(t,e,i){return e===te?ii(function(t,e){var i=me(t),n=$e(t),s=i.visualViewport,o=n.clientWidth,r=n.clientHeight,a=0,l=0;if(s){o=s.width,r=s.height;var c=Oe();(c||!c&&"fixed"===e)&&(a=s.offsetLeft,l=s.offsetTop)}return{width:o,height:r,x:a+Je(t),y:l}}(t,i)):_e(e)?function(t,e){var i=ke(t,!1,"fixed"===e);return i.top=i.top+t.clientTop,i.left=i.left+t.clientLeft,i.bottom=i.top+t.clientHeight,i.right=i.left+t.clientWidth,i.width=t.clientWidth,i.height=t.clientHeight,i.x=i.left,i.y=i.top,i}(e,i):ii(function(t){var e,i=$e(t),n=Ge(t),s=null==(e=t.ownerDocument)?void 0:e.body,o=Ae(i.scrollWidth,i.clientWidth,s?s.scrollWidth:0,s?s.clientWidth:0),r=Ae(i.scrollHeight,i.clientHeight,s?s.scrollHeight:0,s?s.clientHeight:0),a=-n.scrollLeft+Je(t),l=-n.scrollTop;return"rtl"===Se(s||i).direction&&(a+=Ae(i.clientWidth,s?s.clientWidth:0)-o),{width:o,height:r,x:a,y:l}}($e(t)))}function si(t){var e,i=t.reference,n=t.element,s=t.placement,o=s?we(s):null,r=s?We(s):null,a=i.x+i.width/2-n.width/2,l=i.y+i.height/2-n.height/2;switch(o){case Vt:e={x:a,y:i.y-n.height};break;case Ut:e={x:a,y:i.y+i.height};break;case Kt:e={x:i.x+i.width,y:l};break;case Qt:e={x:i.x-n.width,y:l};break;default:e={x:i.x,y:i.y}}var c=o?Pe(o):null;if(null!=c){var h="y"===c?"height":"width";switch(r){case Gt:e[c]=e[c]-(i[h]/2-n[h]/2);break;case Jt:e[c]=e[c]+(i[h]/2-n[h]/2)}}return e}function oi(t,e){void 0===e&&(e={});var i=e,n=i.placement,s=void 0===n?t.placement:n,o=i.strategy,r=void 0===o?t.strategy:o,a=i.boundary,l=void 0===a?Zt:a,c=i.rootBoundary,h=void 0===c?te:c,d=i.elementContext,u=void 0===d?ee:d,f=i.altBoundary,p=void 0!==f&&f,g=i.padding,m=void 0===g?0:g,_=Fe("number"!=typeof m?m:He(m,Xt)),b=u===ee?ie:ee,v=t.rects.popper,y=t.elements[p?b:u],w=function(t,e,i,n){var s="clippingParents"===e?function(t){var e=ei(Ne(t)),i=["absolute","fixed"].indexOf(Se(t).position)>=0&&be(t)?Me(t):t;return _e(i)?e.filter(function(t){return _e(t)&&Le(t,i)&&"body"!==ge(t)}):[]}(t):[].concat(e),o=[].concat(s,[i]),r=o[0],a=o.reduce(function(e,i){var s=ni(t,i,n);return e.top=Ae(s.top,e.top),e.right=Ee(s.right,e.right),e.bottom=Ee(s.bottom,e.bottom),e.left=Ae(s.left,e.left),e},ni(t,r,n));return a.width=a.right-a.left,a.height=a.bottom-a.top,a.x=a.left,a.y=a.top,a}(_e(y)?y:y.contextElement||$e(t.elements.popper),l,h,r),A=ke(t.elements.reference),E=si({reference:A,element:v,placement:s}),C=ii(Object.assign({},v,E)),T=u===ee?C:A,O={top:w.top-T.top+_.top,bottom:T.bottom-w.bottom+_.bottom,left:w.left-T.left+_.left,right:T.right-w.right+_.right},k=t.modifiersData.offset;if(u===ee&&k){var x=k[s];Object.keys(O).forEach(function(t){var e=[Kt,Ut].indexOf(t)>=0?1:-1,i=[Vt,Ut].indexOf(t)>=0?"y":"x";O[t]+=x[i]*e})}return O}function ri(t,e){void 0===e&&(e={});var i=e,n=i.placement,s=i.boundary,o=i.rootBoundary,r=i.padding,a=i.flipVariations,l=i.allowedAutoPlacements,c=void 0===l?se:l,h=We(n),d=h?a?ne:ne.filter(function(t){return We(t)===h}):Xt,u=d.filter(function(t){return c.indexOf(t)>=0});0===u.length&&(u=d);var f=u.reduce(function(e,i){return e[i]=oi(t,{placement:i,boundary:s,rootBoundary:o,padding:r})[we(i)],e},{});return Object.keys(f).sort(function(t,e){return f[t]-f[e]})}const ai={name:"flip",enabled:!0,phase:"main",fn:function(t){var e=t.state,i=t.options,n=t.name;if(!e.modifiersData[n]._skip){for(var s=i.mainAxis,o=void 0===s||s,r=i.altAxis,a=void 0===r||r,l=i.fallbackPlacements,c=i.padding,h=i.boundary,d=i.rootBoundary,u=i.altBoundary,f=i.flipVariations,p=void 0===f||f,g=i.allowedAutoPlacements,m=e.options.placement,_=we(m),b=l||(_!==m&&p?function(t){if(we(t)===Yt)return[];var e=Qe(t);return[Xe(t),e,Xe(e)]}(m):[Qe(m)]),v=[m].concat(b).reduce(function(t,i){return t.concat(we(i)===Yt?ri(e,{placement:i,boundary:h,rootBoundary:d,padding:c,flipVariations:p,allowedAutoPlacements:g}):i)},[]),y=e.rects.reference,w=e.rects.popper,A=new Map,E=!0,C=v[0],T=0;T<v.length;T++){var O=v[T],k=we(O),x=We(O)===Gt,L=[Vt,Ut].indexOf(k)>=0,S=L?"width":"height",D=oi(e,{placement:O,boundary:h,rootBoundary:d,altBoundary:u,padding:c}),$=L?x?Kt:Qt:x?Ut:Vt;y[S]>w[S]&&($=Qe($));var N=Qe($),I=[];if(o&&I.push(D[k]<=0),a&&I.push(D[$]<=0,D[N]<=0),I.every(function(t){return t})){C=O,E=!1;break}A.set(O,I)}if(E)for(var M=function(t){var e=v.find(function(e){var i=A.get(e);if(i)return i.slice(0,t).every(function(t){return t})});if(e)return C=e,"break"},P=p?3:1;P>0&&"break"!==M(P);P--);e.placement!==C&&(e.modifiersData[n]._skip=!0,e.placement=C,e.reset=!0)}},requiresIfExists:["offset"],data:{_skip:!1}};function li(t,e,i){return void 0===i&&(i={x:0,y:0}),{top:t.top-e.height-i.y,right:t.right-e.width+i.x,bottom:t.bottom-e.height+i.y,left:t.left-e.width-i.x}}function ci(t){return[Vt,Kt,Ut,Qt].some(function(e){return t[e]>=0})}const hi={name:"hide",enabled:!0,phase:"main",requiresIfExists:["preventOverflow"],fn:function(t){var e=t.state,i=t.name,n=e.rects.reference,s=e.rects.popper,o=e.modifiersData.preventOverflow,r=oi(e,{elementContext:"reference"}),a=oi(e,{altBoundary:!0}),l=li(r,n),c=li(a,s,o),h=ci(l),d=ci(c);e.modifiersData[i]={referenceClippingOffsets:l,popperEscapeOffsets:c,isReferenceHidden:h,hasPopperEscaped:d},e.attributes.popper=Object.assign({},e.attributes.popper,{"data-popper-reference-hidden":h,"data-popper-escaped":d})}},di={name:"offset",enabled:!0,phase:"main",requires:["popperOffsets"],fn:function(t){var e=t.state,i=t.options,n=t.name,s=i.offset,o=void 0===s?[0,0]:s,r=se.reduce(function(t,i){return t[i]=function(t,e,i){var n=we(t),s=[Qt,Vt].indexOf(n)>=0?-1:1,o="function"==typeof i?i(Object.assign({},e,{placement:t})):i,r=o[0],a=o[1];return r=r||0,a=(a||0)*s,[Qt,Kt].indexOf(n)>=0?{x:a,y:r}:{x:r,y:a}}(i,e.rects,o),t},{}),a=r[e.placement],l=a.x,c=a.y;null!=e.modifiersData.popperOffsets&&(e.modifiersData.popperOffsets.x+=l,e.modifiersData.popperOffsets.y+=c),e.modifiersData[n]=r}},ui={name:"popperOffsets",enabled:!0,phase:"read",fn:function(t){var e=t.state,i=t.name;e.modifiersData[i]=si({reference:e.rects.reference,element:e.rects.popper,placement:e.placement})},data:{}},fi={name:"preventOverflow",enabled:!0,phase:"main",fn:function(t){var e=t.state,i=t.options,n=t.name,s=i.mainAxis,o=void 0===s||s,r=i.altAxis,a=void 0!==r&&r,l=i.boundary,c=i.rootBoundary,h=i.altBoundary,d=i.padding,u=i.tether,f=void 0===u||u,p=i.tetherOffset,g=void 0===p?0:p,m=oi(e,{boundary:l,rootBoundary:c,padding:d,altBoundary:h}),_=we(e.placement),b=We(e.placement),v=!b,y=Pe(_),w="x"===y?"y":"x",A=e.modifiersData.popperOffsets,E=e.rects.reference,C=e.rects.popper,T="function"==typeof g?g(Object.assign({},e.rects,{placement:e.placement})):g,O="number"==typeof T?{mainAxis:T,altAxis:T}:Object.assign({mainAxis:0,altAxis:0},T),k=e.modifiersData.offset?e.modifiersData.offset[e.placement]:null,x={x:0,y:0};if(A){if(o){var L,S="y"===y?Vt:Qt,D="y"===y?Ut:Kt,$="y"===y?"height":"width",N=A[y],I=N+m[S],M=N-m[D],P=f?-C[$]/2:0,j=b===Gt?E[$]:C[$],F=b===Gt?-C[$]:-E[$],H=e.elements.arrow,B=f&&H?xe(H):{width:0,height:0},W=e.modifiersData["arrow#persistent"]?e.modifiersData["arrow#persistent"].padding:{top:0,right:0,bottom:0,left:0},z=W[S],q=W[D],R=je(0,E[$],B[$]),V=v?E[$]/2-P-R-z-O.mainAxis:j-R-z-O.mainAxis,U=v?-E[$]/2+P+R+q+O.mainAxis:F+R+q+O.mainAxis,K=e.elements.arrow&&Me(e.elements.arrow),Q=K?"y"===y?K.clientTop||0:K.clientLeft||0:0,Y=null!=(L=null==k?void 0:k[y])?L:0,X=N+U-Y,G=je(f?Ee(I,N+V-Y-Q):I,N,f?Ae(M,X):M);A[y]=G,x[y]=G-N}if(a){var J,Z="x"===y?Vt:Qt,tt="x"===y?Ut:Kt,et=A[w],it="y"===w?"height":"width",nt=et+m[Z],st=et-m[tt],ot=-1!==[Vt,Qt].indexOf(_),rt=null!=(J=null==k?void 0:k[w])?J:0,at=ot?nt:et-E[it]-C[it]-rt+O.altAxis,lt=ot?et+E[it]+C[it]-rt-O.altAxis:st,ct=f&&ot?function(t,e,i){var n=je(t,e,i);return n>i?i:n}(at,et,lt):je(f?at:nt,et,f?lt:st);A[w]=ct,x[w]=ct-et}e.modifiersData[n]=x}},requiresIfExists:["offset"]};function pi(t,e,i){void 0===i&&(i=!1);var n,s,o=be(e),r=be(e)&&function(t){var e=t.getBoundingClientRect(),i=Ce(e.width)/t.offsetWidth||1,n=Ce(e.height)/t.offsetHeight||1;return 1!==i||1!==n}(e),a=$e(e),l=ke(t,r,i),c={scrollLeft:0,scrollTop:0},h={x:0,y:0};return(o||!o&&!i)&&(("body"!==ge(e)||Ze(a))&&(c=(n=e)!==me(n)&&be(n)?{scrollLeft:(s=n).scrollLeft,scrollTop:s.scrollTop}:Ge(n)),be(e)?((h=ke(e,!0)).x+=e.clientLeft,h.y+=e.clientTop):a&&(h.x=Je(a))),{x:l.left+c.scrollLeft-h.x,y:l.top+c.scrollTop-h.y,width:l.width,height:l.height}}function gi(t){var e=new Map,i=new Set,n=[];function s(t){i.add(t.name),[].concat(t.requires||[],t.requiresIfExists||[]).forEach(function(t){if(!i.has(t)){var n=e.get(t);n&&s(n)}}),n.push(t)}return t.forEach(function(t){e.set(t.name,t)}),t.forEach(function(t){i.has(t.name)||s(t)}),n}var mi={placement:"bottom",modifiers:[],strategy:"absolute"};function _i(){for(var t=arguments.length,e=new Array(t),i=0;i<t;i++)e[i]=arguments[i];return!e.some(function(t){return!(t&&"function"==typeof t.getBoundingClientRect)})}function bi(t){void 0===t&&(t={});var e=t,i=e.defaultModifiers,n=void 0===i?[]:i,s=e.defaultOptions,o=void 0===s?mi:s;return function(t,e,i){void 0===i&&(i=o);var s,r,a={placement:"bottom",orderedModifiers:[],options:Object.assign({},mi,o),modifiersData:{},elements:{reference:t,popper:e},attributes:{},styles:{}},l=[],c=!1,h={state:a,setOptions:function(i){var s="function"==typeof i?i(a.options):i;d(),a.options=Object.assign({},o,a.options,s),a.scrollParents={reference:_e(t)?ei(t):t.contextElement?ei(t.contextElement):[],popper:ei(e)};var r,c,u=function(t){var e=gi(t);return pe.reduce(function(t,i){return t.concat(e.filter(function(t){return t.phase===i}))},[])}((r=[].concat(n,a.options.modifiers),c=r.reduce(function(t,e){var i=t[e.name];return t[e.name]=i?Object.assign({},i,e,{options:Object.assign({},i.options,e.options),data:Object.assign({},i.data,e.data)}):e,t},{}),Object.keys(c).map(function(t){return c[t]})));return a.orderedModifiers=u.filter(function(t){return t.enabled}),a.orderedModifiers.forEach(function(t){var e=t.name,i=t.options,n=void 0===i?{}:i,s=t.effect;if("function"==typeof s){var o=s({state:a,name:e,instance:h,options:n});l.push(o||function(){})}}),h.update()},forceUpdate:function(){if(!c){var t=a.elements,e=t.reference,i=t.popper;if(_i(e,i)){a.rects={reference:pi(e,Me(i),"fixed"===a.options.strategy),popper:xe(i)},a.reset=!1,a.placement=a.options.placement,a.orderedModifiers.forEach(function(t){return a.modifiersData[t.name]=Object.assign({},t.data)});for(var n=0;n<a.orderedModifiers.length;n++)if(!0!==a.reset){var s=a.orderedModifiers[n],o=s.fn,r=s.options,l=void 0===r?{}:r,d=s.name;"function"==typeof o&&(a=o({state:a,options:l,name:d,instance:h})||a)}else a.reset=!1,n=-1}}},update:(s=function(){return new Promise(function(t){h.forceUpdate(),t(a)})},function(){return r||(r=new Promise(function(t){Promise.resolve().then(function(){r=void 0,t(s())})})),r}),destroy:function(){d(),c=!0}};if(!_i(t,e))return h;function d(){l.forEach(function(t){return t()}),l=[]}return h.setOptions(i).then(function(t){!c&&i.onFirstUpdate&&i.onFirstUpdate(t)}),h}}var vi=bi(),yi=bi({defaultModifiers:[Ue,ui,Re,ye]}),wi=bi({defaultModifiers:[Ue,ui,Re,ye,di,ai,fi,Be,hi]});const Ai=Object.freeze(Object.defineProperty({__proto__:null,afterMain:he,afterRead:ae,afterWrite:fe,applyStyles:ye,arrow:Be,auto:Yt,basePlacements:Xt,beforeMain:le,beforeRead:oe,beforeWrite:de,bottom:Ut,clippingParents:Zt,computeStyles:Re,createPopper:wi,createPopperBase:vi,createPopperLite:yi,detectOverflow:oi,end:Jt,eventListeners:Ue,flip:ai,hide:hi,left:Qt,main:ce,modifierPhases:pe,offset:di,placements:se,popper:ee,popperGenerator:bi,popperOffsets:ui,preventOverflow:fi,read:re,reference:ie,right:Kt,start:Gt,top:Vt,variationPlacements:ne,viewport:te,write:ue},Symbol.toStringTag,{value:"Module"})),Ei="dropdown",Ci=".coreui.dropdown",Ti=".data-api",Oi="ArrowUp",ki="ArrowDown",xi=`hide${Ci}`,Li=`hidden${Ci}`,Si=`show${Ci}`,Di=`shown${Ci}`,$i=`click${Ci}${Ti}`,Ni=`keydown${Ci}${Ti}`,Ii=`keyup${Ci}${Ti}`,Mi="show",Pi='[data-coreui-toggle="dropdown"]:not(.disabled):not(:disabled)',ji=`${Pi}.${Mi}`,Fi=".dropdown-menu",Hi=g()?"top-end":"top-start",Bi=g()?"top-start":"top-end",Wi=g()?"bottom-end":"bottom-start",zi=g()?"bottom-start":"bottom-end",qi=g()?"left-start":"right-start",Ri=g()?"right-start":"left-start",Vi={autoClose:!0,boundary:"clippingParents",display:"dynamic",offset:[0,2],popperConfig:null,reference:"toggle"},Ui={autoClose:"(boolean|string)",boundary:"(string|element)",display:"string",offset:"(array|string|function)",popperConfig:"(null|object|function)",reference:"(string|element|object)"};class Ki extends W{constructor(t,e){super(t,e),this._popper=null,this._parent=this._element.parentNode,this._menu=q.next(this._element,Fi)[0]||q.prev(this._element,Fi)[0]||q.findOne(Fi,this._parent),this._inNavbar=this._detectNavbar()}static get Default(){return Vi}static get DefaultType(){return Ui}static get NAME(){return Ei}toggle(){return this._isShown()?this.hide():this.show()}show(){if(c(this._element)||this._isShown())return;const t={relatedTarget:this._element};if(!M.trigger(this._element,Si,t).defaultPrevented){if(this._createPopper(),"ontouchstart"in document.documentElement&&!this._parent.closest(".navbar-nav"))for(const t of[].concat(...document.body.children))M.on(t,"mouseover",d);this._element.focus(),this._element.setAttribute("aria-expanded",!0),this._menu.classList.add(Mi),this._element.classList.add(Mi),M.trigger(this._element,Di,t)}}hide(){if(c(this._element)||!this._isShown())return;const t={relatedTarget:this._element};this._completeHide(t)}dispose(){this._popper&&this._popper.destroy(),super.dispose()}update(){this._inNavbar=this._detectNavbar(),this._popper&&this._popper.update()}_completeHide(t){if(!M.trigger(this._element,xi,t).defaultPrevented){if("ontouchstart"in document.documentElement)for(const t of[].concat(...document.body.children))M.off(t,"mouseover",d);this._popper&&this._popper.destroy(),this._menu.classList.remove(Mi),this._element.classList.remove(Mi),this._element.setAttribute("aria-expanded","false"),H.removeDataAttribute(this._menu,"popper"),M.trigger(this._element,Li,t)}}_getConfig(t){if("object"==typeof(t=super._getConfig(t)).reference&&!r(t.reference)&&"function"!=typeof t.reference.getBoundingClientRect)throw new TypeError(`${Ei.toUpperCase()}: Option "reference" provided type "object" without a required "getBoundingClientRect" method.`);return t}_createPopper(){if(void 0===Ai)throw new TypeError("CoreUI's dropdowns require Popper (https://popper.js.org/docs/v2/)");let t=this._element;"parent"===this._config.reference?t=this._parent:r(this._config.reference)?t=a(this._config.reference):"object"==typeof this._config.reference&&(t=this._config.reference);const e=this._getPopperConfig();this._popper=wi(t,this._menu,e)}_isShown(){return this._menu.classList.contains(Mi)}_getPlacement(){const t=this._parent;if(t.classList.contains("dropend"))return qi;if(t.classList.contains("dropstart"))return Ri;if(t.classList.contains("dropup-center"))return"top";if(t.classList.contains("dropdown-center"))return"bottom";const e="end"===getComputedStyle(this._menu).getPropertyValue("--cui-position").trim();return t.classList.contains("dropup")?e?Bi:Hi:e?zi:Wi}_detectNavbar(){return null!==this._element.closest(".navbar")}_getOffset(){const{offset:t}=this._config;return"string"==typeof t?t.split(",").map(t=>Number.parseInt(t,10)):"function"==typeof t?e=>t(e,this._element):t}_getPopperConfig(){const t={placement:this._getPlacement(),modifiers:[{name:"preventOverflow",options:{boundary:this._config.boundary}},{name:"offset",options:{offset:this._getOffset()}}]};return(this._inNavbar||"static"===this._config.display)&&(H.setDataAttribute(this._menu,"popper","static"),t.modifiers=[{name:"applyStyles",enabled:!1}]),{...t,..._(this._config.popperConfig,[void 0,t])}}_selectMenuItem({key:t,target:e}){const i=q.find(".dropdown-menu .dropdown-item:not(.disabled):not(:disabled)",this._menu).filter(t=>l(t));i.length&&v(i,e,t===ki,!i.includes(e)).focus()}static jQueryInterface(t){return this.each(function(){const e=Ki.getOrCreateInstance(this,t);if("string"==typeof t){if(void 0===e[t])throw new TypeError(`No method named "${t}"`);e[t]()}})}static clearMenus(t){if(2===t.button||"keyup"===t.type&&"Tab"!==t.key)return;const e=q.find(ji);for(const i of e){const e=Ki.getInstance(i);if(!e||!1===e._config.autoClose)continue;const n=t.composedPath(),s=n.includes(e._menu);if(n.includes(e._element)||"inside"===e._config.autoClose&&!s||"outside"===e._config.autoClose&&s)continue;if(e._menu.contains(t.target)&&("keyup"===t.type&&"Tab"===t.key||/input|select|option|textarea|form/i.test(t.target.tagName)))continue;const o={relatedTarget:e._element};"click"===t.type&&(o.clickEvent=t),e._completeHide(o)}}static dataApiKeydownHandler(t){const e=/input|textarea/i.test(t.target.tagName),i="Escape"===t.key,n=[Oi,ki].includes(t.key);if(!n&&!i)return;if(e&&!i)return;t.preventDefault();const s=this.matches(Pi)?this:q.prev(this,Pi)[0]||q.next(this,Pi)[0]||q.findOne(Pi,t.delegateTarget.parentNode),o=Ki.getOrCreateInstance(s);if(n)return t.stopPropagation(),o.show(),void o._selectMenuItem(t);o._isShown()&&(t.stopPropagation(),o.hide(),s.focus())}}M.on(document,Ni,Pi,Ki.dataApiKeydownHandler),M.on(document,Ni,Fi,Ki.dataApiKeydownHandler),M.on(document,$i,Ki.clearMenus),M.on(document,Ii,Ki.clearMenus),M.on(document,$i,Pi,function(t){t.preventDefault(),Ki.getOrCreateInstance(this).toggle()}),m(Ki);const Qi="backdrop",Yi="show",Xi=`mousedown.coreui.${Qi}`,Gi={className:"modal-backdrop",clickCallback:null,isAnimated:!1,isVisible:!0,rootElement:"body"},Ji={className:"string",clickCallback:"(function|null)",isAnimated:"boolean",isVisible:"boolean",rootElement:"(element|string)"};class Zi extends B{constructor(t){super(),this._config=this._getConfig(t),this._isAppended=!1,this._element=null}static get Default(){return Gi}static get DefaultType(){return Ji}static get NAME(){return Qi}show(t){if(!this._config.isVisible)return void _(t);this._append();const e=this._getElement();this._config.isAnimated&&u(e),e.classList.add(Yi),this._emulateAnimation(()=>{_(t)})}hide(t){this._config.isVisible?(this._getElement().classList.remove(Yi),this._emulateAnimation(()=>{this.dispose(),_(t)})):_(t)}dispose(){this._isAppended&&(M.off(this._element,Xi),this._element.remove(),this._isAppended=!1)}_getElement(){if(!this._element){const t=document.createElement("div");t.className=this._config.className,this._config.isAnimated&&t.classList.add("fade"),this._element=t}return this._element}_configAfterMerge(t){return t.rootElement=a(t.rootElement),t}_append(){if(this._isAppended)return;const t=this._getElement();this._config.rootElement.append(t),M.on(t,Xi,()=>{_(this._config.clickCallback)}),this._isAppended=!0}_emulateAnimation(t){b(t,this._getElement(),this._config.isAnimated)}}const tn=".coreui.focustrap",en=`focusin${tn}`,nn=`keydown.tab${tn}`,sn="backward",on={autofocus:!0,trapElement:null},rn={autofocus:"boolean",trapElement:"element"};class an extends B{constructor(t){super(),this._config=this._getConfig(t),this._isActive=!1,this._lastTabNavDirection=null}static get Default(){return on}static get DefaultType(){return rn}static get NAME(){return"focustrap"}activate(){this._isActive||(this._config.autofocus&&this._config.trapElement.focus(),M.off(document,tn),M.on(document,en,t=>this._handleFocusin(t)),M.on(document,nn,t=>this._handleKeydown(t)),this._isActive=!0)}deactivate(){this._isActive&&(this._isActive=!1,M.off(document,tn))}_handleFocusin(t){const{trapElement:e}=this._config;if(t.target===document||t.target===e||e.contains(t.target))return;const i=q.focusableChildren(e);0===i.length?e.focus():this._lastTabNavDirection===sn?i[i.length-1].focus():i[0].focus()}_handleKeydown(t){"Tab"===t.key&&(this._lastTabNavDirection=t.shiftKey?sn:"forward")}}const ln=".fixed-top, .fixed-bottom, .is-fixed, .sticky-top",cn=".sticky-top",hn="padding-right",dn="margin-right";class un{constructor(){this._element=document.body}getWidth(){const t=document.documentElement.clientWidth;return Math.abs(window.innerWidth-t)}hide(){const t=this.getWidth();this._disableOverFlow(),this._setElementAttributes(this._element,hn,e=>e+t),this._setElementAttributes(ln,hn,e=>e+t),this._setElementAttributes(cn,dn,e=>e-t)}reset(){this._resetElementAttributes(this._element,"overflow"),this._resetElementAttributes(this._element,hn),this._resetElementAttributes(ln,hn),this._resetElementAttributes(cn,dn)}isOverflowing(){return this.getWidth()>0}_disableOverFlow(){this._saveInitialAttribute(this._element,"overflow"),this._element.style.overflow="hidden"}_setElementAttributes(t,e,i){const n=this.getWidth();this._applyManipulationCallback(t,t=>{if(t!==this._element&&window.innerWidth>t.clientWidth+n)return;this._saveInitialAttribute(t,e);const s=window.getComputedStyle(t).getPropertyValue(e);t.style.setProperty(e,`${i(Number.parseFloat(s))}px`)})}_saveInitialAttribute(t,e){const i=t.style.getPropertyValue(e);i&&H.setDataAttribute(t,e,i)}_resetElementAttributes(t,e){this._applyManipulationCallback(t,t=>{const i=H.getDataAttribute(t,e);null!==i?(H.removeDataAttribute(t,e),t.style.setProperty(e,i)):t.style.removeProperty(e)})}_applyManipulationCallback(t,e){if(r(t))e(t);else for(const i of q.find(t,this._element))e(i)}}const fn=".coreui.modal",pn=`hide${fn}`,gn=`hidePrevented${fn}`,mn=`hidden${fn}`,_n=`show${fn}`,bn=`shown${fn}`,vn=`resize${fn}`,yn=`click.dismiss${fn}`,wn=`mousedown.dismiss${fn}`,An=`keydown.dismiss${fn}`,En=`click${fn}.data-api`,Cn="modal-open",Tn="show",On="modal-static",kn={backdrop:!0,focus:!0,keyboard:!0},xn={backdrop:"(boolean|string)",focus:"boolean",keyboard:"boolean"};class Ln extends W{constructor(t,e){super(t,e),this._dialog=q.findOne(".modal-dialog",this._element),this._backdrop=this._initializeBackDrop(),this._focustrap=this._initializeFocusTrap(),this._isShown=!1,this._isTransitioning=!1,this._scrollBar=new un,this._addEventListeners()}static get Default(){return kn}static get DefaultType(){return xn}static get NAME(){return"modal"}toggle(t){return this._isShown?this.hide():this.show(t)}show(t){this._isShown||this._isTransitioning||M.trigger(this._element,_n,{relatedTarget:t}).defaultPrevented||(this._isShown=!0,this._isTransitioning=!0,this._scrollBar.hide(),document.body.classList.add(Cn),this._adjustDialog(),this._backdrop.show(()=>this._showElement(t)))}hide(){this._isShown&&!this._isTransitioning&&(M.trigger(this._element,pn).defaultPrevented||(this._isShown=!1,this._isTransitioning=!0,this._focustrap.deactivate(),this._element.classList.remove(Tn),this._queueCallback(()=>this._hideModal(),this._element,this._isAnimated())))}dispose(){M.off(window,fn),M.off(this._dialog,fn),this._backdrop.dispose(),this._focustrap.deactivate(),super.dispose()}handleUpdate(){this._adjustDialog()}_initializeBackDrop(){return new Zi({isVisible:Boolean(this._config.backdrop),isAnimated:this._isAnimated()})}_initializeFocusTrap(){return new an({trapElement:this._element})}_showElement(t){document.body.contains(this._element)||document.body.append(this._element),this._element.style.display="block",this._element.removeAttribute("aria-hidden"),this._element.setAttribute("aria-modal",!0),this._element.setAttribute("role","dialog"),this._element.scrollTop=0;const e=q.findOne(".modal-body",this._dialog);e&&(e.scrollTop=0),u(this._element),this._element.classList.add(Tn),this._queueCallback(()=>{this._config.focus&&this._focustrap.activate(),this._isTransitioning=!1,M.trigger(this._element,bn,{relatedTarget:t})},this._dialog,this._isAnimated())}_addEventListeners(){M.on(this._element,An,t=>{"Escape"===t.key&&(this._config.keyboard?this.hide():this._triggerBackdropTransition())}),M.on(window,vn,()=>{this._isShown&&!this._isTransitioning&&this._adjustDialog()}),M.on(this._element,wn,t=>{M.one(this._element,yn,e=>{this._element===t.target&&this._element===e.target&&("static"!==this._config.backdrop?this._config.backdrop&&this.hide():this._triggerBackdropTransition())})})}_hideModal(){this._element.style.display="none",this._element.setAttribute("aria-hidden",!0),this._element.removeAttribute("aria-modal"),this._element.removeAttribute("role"),this._isTransitioning=!1,this._backdrop.hide(()=>{document.body.classList.remove(Cn),this._resetAdjustments(),this._scrollBar.reset(),M.trigger(this._element,mn)})}_isAnimated(){return this._element.classList.contains("fade")}_triggerBackdropTransition(){if(M.trigger(this._element,gn).defaultPrevented)return;const t=this._element.scrollHeight>document.documentElement.clientHeight,e=this._element.style.overflowY;"hidden"===e||this._element.classList.contains(On)||(t||(this._element.style.overflowY="hidden"),this._element.classList.add(On),this._queueCallback(()=>{this._element.classList.remove(On),this._queueCallback(()=>{this._element.style.overflowY=e},this._dialog)},this._dialog),this._element.focus())}_adjustDialog(){const t=this._element.scrollHeight>document.documentElement.clientHeight,e=this._scrollBar.getWidth(),i=e>0;if(i&&!t){const t=g()?"paddingLeft":"paddingRight";this._element.style[t]=`${e}px`}if(!i&&t){const t=g()?"paddingRight":"paddingLeft";this._element.style[t]=`${e}px`}}_resetAdjustments(){this._element.style.paddingLeft="",this._element.style.paddingRight=""}static jQueryInterface(t,e){return this.each(function(){const i=Ln.getOrCreateInstance(this,t);if("string"==typeof t){if(void 0===i[t])throw new TypeError(`No method named "${t}"`);i[t](e)}})}}M.on(document,En,'[data-coreui-toggle="modal"]',function(t){const e=q.getElementFromSelector(this);["A","AREA"].includes(this.tagName)&&t.preventDefault(),M.one(e,_n,t=>{t.defaultPrevented||M.one(e,mn,()=>{l(this)&&this.focus()})});const i=q.findOne(".modal.show");i&&Ln.getInstance(i).hide(),Ln.getOrCreateInstance(e).toggle(this)}),R(Ln),m(Ln);const Sn="coreui.navigation",Dn=`.${Sn}`,$n=".data-api",Nn={activeLinksExact:!0,groupsAutoCollapse:!0},In={activeLinksExact:"boolean",groupsAutoCollapse:"(string|boolean)"},Mn="active",Pn="show",jn="nav-group-toggle",Fn=`click${Dn}${$n}`,Hn=`load${Dn}${$n}`,Bn=".nav-group",Wn=".nav-group-items",zn=".nav-group-toggle";class qn extends W{constructor(t,i){super(t),this._config=this._getConfig(i),this._setActiveLink(),this._addEventListeners(),e.set(t,Sn,this)}static get Default(){return Nn}static get DATA_KEY(){return Sn}static get DefaultType(){return In}static get NAME(){return"navigation"}_setActiveLink(){for(const t of Array.from(this._element.querySelectorAll(".nav-link"))){if(t.classList.contains(jn))continue;let e=String(window.location);const i=/\?./,n=/#./;(/\?.*=/.test(e)||i.test(e))&&(e=e.split("?")[0]),n.test(e)&&(e=e.split("#")[0]),this._config.activeLinksExact&&t.href===e&&(t.classList.add(Mn),Array.from(this._getParents(t,Bn)).forEach(t=>{t.classList.add(Pn),t.setAttribute("aria-expanded",!0)})),!this._config.activeLinksExact&&e.startsWith(t.href)&&(t.classList.add(Mn),Array.from(this._getParents(t,Bn)).forEach(t=>{t.classList.add(Pn),t.setAttribute("aria-expanded",!0)}))}}_getParents(t,e){const i=[];for(;t&&t!==document;t=t.parentNode)e?t.matches(e)&&i.push(t):i.push(t);return i}_getAllSiblings(t,e){const i=[];t=t.parentNode.firstChild;do{3!==t.nodeType&&8!==t.nodeType&&(e&&!e(t)||i.push(t))}while(t=t.nextSibling);return i}_getChildren(t,e){const i=[];for(;t;t=t.nextSibling)1===t.nodeType&&t!==e&&i.push(t);return i}_getSiblings(t,e){return this._getChildren(t.parentNode.firstChild,t).filter(e)}_slideDown(t){t.style.height="auto";const e=t.clientHeight;t.style.height="0px",setTimeout(()=>{t.style.height=`${e}px`},0),this._queueCallback(()=>{t.style.height="auto"},t,!0)}_slideUp(t,e){const i=t.clientHeight;t.style.height=`${i}px`,setTimeout(()=>{t.style.height="0px"},0),this._queueCallback(()=>{"function"==typeof e&&e()},t,!0)}_toggleGroupItems(t){let e=t.target;e.classList.contains(jn)||(e=e.closest(zn));const i=t=>Boolean(t.classList.contains("nav-group")&&t.classList.contains(Pn));if(!0===this._config.groupsAutoCollapse)for(const t of this._getSiblings(e.parentNode,i))this._slideUp(q.findOne(Wn,t),()=>{t.classList.remove(Pn),t.setAttribute("aria-expanded",!1)});e.parentNode.classList.contains(Pn)?this._slideUp(q.findOne(Wn,e.parentNode),()=>{e.parentNode.classList.remove(Pn),e.parentNode.setAttribute("aria-expanded",!1)}):(e.parentNode.classList.add(Pn),e.parentNode.setAttribute("aria-expanded",!0),this._slideDown(q.findOne(Wn,e.parentNode)))}_addEventListeners(){M.on(this._element,Fn,zn,t=>{t.preventDefault(),this._toggleGroupItems(t,this)})}static navigationInterface(t,e){const i=qn.getOrCreateInstance(t,e);if("string"==typeof e){if(void 0===i[e])throw new TypeError(`No method named "${e}"`);i[e]()}}static jQueryInterface(t){return this.each(function(){qn.navigationInterface(this,t)})}}M.on(window,Hn,()=>{for(const t of Array.from(document.querySelectorAll('[data-coreui="navigation"]')))qn.navigationInterface(t)}),m(qn);const Rn=".coreui.offcanvas",Vn=".data-api",Un=`load${Rn}${Vn}`,Kn="show",Qn="showing",Yn="hiding",Xn=".offcanvas.show",Gn=`show${Rn}`,Jn=`shown${Rn}`,Zn=`hide${Rn}`,ts=`hidePrevented${Rn}`,es=`hidden${Rn}`,is=`resize${Rn}`,ns=`click${Rn}${Vn}`,ss=`keydown.dismiss${Rn}`,os={backdrop:!0,keyboard:!0,scroll:!1},rs={backdrop:"(boolean|string)",keyboard:"boolean",scroll:"boolean"};class as extends W{constructor(t,e){super(t,e),this._isShown=!1,this._backdrop=this._initializeBackDrop(),this._focustrap=this._initializeFocusTrap(),this._addEventListeners()}static get Default(){return os}static get DefaultType(){return rs}static get NAME(){return"offcanvas"}toggle(t){return this._isShown?this.hide():this.show(t)}show(t){this._isShown||M.trigger(this._element,Gn,{relatedTarget:t}).defaultPrevented||(this._isShown=!0,this._backdrop.show(),this._config.scroll||(new un).hide(),this._element.setAttribute("aria-modal",!0),this._element.setAttribute("role","dialog"),this._element.classList.add(Qn),this._queueCallback(()=>{this._config.scroll&&!this._config.backdrop||this._focustrap.activate(),this._element.classList.add(Kn),this._element.classList.remove(Qn),M.trigger(this._element,Jn,{relatedTarget:t})},this._element,!0))}hide(){this._isShown&&(M.trigger(this._element,Zn).defaultPrevented||(this._focustrap.deactivate(),this._element.blur(),this._isShown=!1,this._element.classList.add(Yn),this._backdrop.hide(),this._queueCallback(()=>{this._element.classList.remove(Kn,Yn),this._element.removeAttribute("aria-modal"),this._element.removeAttribute("role"),this._config.scroll||(new un).reset(),M.trigger(this._element,es)},this._element,!0)))}dispose(){this._backdrop.dispose(),this._focustrap.deactivate(),super.dispose()}_initializeBackDrop(){const t=Boolean(this._config.backdrop);return new Zi({className:"offcanvas-backdrop",isVisible:t,isAnimated:!0,rootElement:this._element.parentNode,clickCallback:t?()=>{"static"!==this._config.backdrop?this.hide():M.trigger(this._element,ts)}:null})}_initializeFocusTrap(){return new an({trapElement:this._element})}_addEventListeners(){M.on(this._element,ss,t=>{"Escape"===t.key&&(this._config.keyboard?this.hide():M.trigger(this._element,ts))})}static jQueryInterface(t){return this.each(function(){const e=as.getOrCreateInstance(this,t);if("string"==typeof t){if(void 0===e[t]||t.startsWith("_")||"constructor"===t)throw new TypeError(`No method named "${t}"`);e[t](this)}})}}M.on(document,ns,'[data-coreui-toggle="offcanvas"]',function(t){const e=q.getElementFromSelector(this);if(["A","AREA"].includes(this.tagName)&&t.preventDefault(),c(this))return;M.one(e,es,()=>{l(this)&&this.focus()});const i=q.findOne(Xn);i&&i!==e&&as.getInstance(i).hide(),as.getOrCreateInstance(e).toggle(this)}),M.on(window,Un,()=>{for(const t of q.find(Xn))as.getOrCreateInstance(t).show()}),M.on(window,is,()=>{for(const t of q.find("[aria-modal][class*=show][class*=offcanvas-]"))"fixed"!==getComputedStyle(t).position&&as.getOrCreateInstance(t).hide()}),R(as),m(as);const ls={"*":["class","dir","id","lang","role",/^aria-[\w-]*$/i],a:["target","href","title","rel"],area:[],b:[],br:[],col:[],code:[],dd:[],div:[],dl:[],dt:[],em:[],hr:[],h1:[],h2:[],h3:[],h4:[],h5:[],h6:[],i:[],img:["src","srcset","alt","title","width","height"],li:[],ol:[],p:[],pre:[],s:[],small:[],span:[],sub:[],sup:[],strong:[],u:[],ul:[]},cs=new Set(["background","cite","href","itemtype","longdesc","poster","src","xlink:href"]),hs=/^(?!javascript:)(?:[a-z0-9+.-]+:|[^&:/?#]*(?:[/?#]|$))/i,ds=(t,e)=>{const i=t.nodeName.toLowerCase();return e.includes(i)?!cs.has(i)||Boolean(hs.test(t.nodeValue)):e.filter(t=>t instanceof RegExp).some(t=>t.test(i))},us={allowList:ls,content:{},extraClass:"",html:!1,sanitize:!0,sanitizeFn:null,template:"<div></div>"},fs={allowList:"object",content:"object",extraClass:"(string|function)",html:"boolean",sanitize:"boolean",sanitizeFn:"(null|function)",template:"string"},ps={entry:"(string|element|function|null)",selector:"(string|element)"};class gs extends B{constructor(t){super(),this._config=this._getConfig(t)}static get Default(){return us}static get DefaultType(){return fs}static get NAME(){return"TemplateFactory"}getContent(){return Object.values(this._config.content).map(t=>this._resolvePossibleFunction(t)).filter(Boolean)}hasContent(){return this.getContent().length>0}changeContent(t){return this._checkContent(t),this._config.content={...this._config.content,...t},this}toHtml(){const t=document.createElement("div");t.innerHTML=this._maybeSanitize(this._config.template);for(const[e,i]of Object.entries(this._config.content))this._setContent(t,i,e);const e=t.children[0],i=this._resolvePossibleFunction(this._config.extraClass);return i&&e.classList.add(...i.split(" ")),e}_typeCheckConfig(t){super._typeCheckConfig(t),this._checkContent(t.content)}_checkContent(t){for(const[e,i]of Object.entries(t))super._typeCheckConfig({selector:e,entry:i},ps)}_setContent(t,e,i){const n=q.findOne(i,t);n&&((e=this._resolvePossibleFunction(e))?r(e)?this._putElementInTemplate(a(e),n):this._config.html?n.innerHTML=this._maybeSanitize(e):n.textContent=e:n.remove())}_maybeSanitize(t){return this._config.sanitize?function(t,e,i){if(!t.length)return t;if(i&&"function"==typeof i)return i(t);const n=(new window.DOMParser).parseFromString(t,"text/html"),s=[].concat(...n.body.querySelectorAll("*"));for(const t of s){const i=t.nodeName.toLowerCase();if(!Object.keys(e).includes(i)){t.remove();continue}const n=[].concat(...t.attributes),s=[].concat(e["*"]||[],e[i]||[]);for(const e of n)ds(e,s)||t.removeAttribute(e.nodeName)}return n.body.innerHTML}(t,this._config.allowList,this._config.sanitizeFn):t}_resolvePossibleFunction(t){return _(t,[void 0,this])}_putElementInTemplate(t,e){if(this._config.html)return e.innerHTML="",void e.append(t);e.textContent=t.textContent}}const ms=new Set(["sanitize","allowList","sanitizeFn"]),_s="fade",bs="show",vs=".tooltip-inner",ys=".modal",ws="hide.coreui.modal",As="hover",Es="focus",Cs="click",Ts={AUTO:"auto",TOP:"top",RIGHT:g()?"left":"right",BOTTOM:"bottom",LEFT:g()?"right":"left"},Os={allowList:ls,animation:!0,boundary:"clippingParents",container:!1,customClass:"",delay:0,fallbackPlacements:["top","right","bottom","left"],html:!1,offset:[0,6],placement:"top",popperConfig:null,sanitize:!0,sanitizeFn:null,selector:!1,template:'<div class="tooltip" role="tooltip"><div class="tooltip-arrow"></div><div class="tooltip-inner"></div></div>',title:"",trigger:"hover focus"},ks={allowList:"object",animation:"boolean",boundary:"(string|element)",container:"(string|element|boolean)",customClass:"(string|function)",delay:"(number|object)",fallbackPlacements:"array",html:"boolean",offset:"(array|string|function)",placement:"(string|function)",popperConfig:"(null|object|function)",sanitize:"boolean",sanitizeFn:"(null|function)",selector:"(string|boolean)",template:"string",title:"(string|element|function)",trigger:"string"};class xs extends W{constructor(t,e){if(void 0===Ai)throw new TypeError("CoreUI's dropdowns require Popper (https://popper.js.org/docs/v2/)");super(t,e),this._isEnabled=!0,this._timeout=0,this._isHovered=null,this._activeTrigger={},this._popper=null,this._templateFactory=null,this._newContent=null,this.tip=null,this._setListeners(),this._config.selector||this._fixTitle()}static get Default(){return Os}static get DefaultType(){return ks}static get NAME(){return"tooltip"}enable(){this._isEnabled=!0}disable(){this._isEnabled=!1}toggleEnabled(){this._isEnabled=!this._isEnabled}toggle(){this._isEnabled&&(this._isShown()?this._leave():this._enter())}dispose(){clearTimeout(this._timeout),M.off(this._element.closest(ys),ws,this._hideModalHandler),this._element.getAttribute("data-coreui-original-title")&&this._element.setAttribute("title",this._element.getAttribute("data-coreui-original-title")),this._disposePopper(),super.dispose()}show(){if("none"===this._element.style.display)throw new Error("Please use show on visible elements");if(!this._isWithContent()||!this._isEnabled)return;const t=M.trigger(this._element,this.constructor.eventName("show")),e=(h(this._element)||this._element.ownerDocument.documentElement).contains(this._element);if(t.defaultPrevented||!e)return;this._disposePopper();const i=this._getTipElement();this._element.setAttribute("aria-describedby",i.getAttribute("id"));const{container:n}=this._config;if(this._element.ownerDocument.documentElement.contains(this.tip)||(n.append(i),M.trigger(this._element,this.constructor.eventName("inserted"))),this._popper=this._createPopper(i),i.classList.add(bs),"ontouchstart"in document.documentElement)for(const t of[].concat(...document.body.children))M.on(t,"mouseover",d);this._queueCallback(()=>{M.trigger(this._element,this.constructor.eventName("shown")),!1===this._isHovered&&this._leave(),this._isHovered=!1},this.tip,this._isAnimated())}hide(){if(this._isShown()&&!M.trigger(this._element,this.constructor.eventName("hide")).defaultPrevented){if(this._getTipElement().classList.remove(bs),"ontouchstart"in document.documentElement)for(const t of[].concat(...document.body.children))M.off(t,"mouseover",d);this._activeTrigger[Cs]=!1,this._activeTrigger[Es]=!1,this._activeTrigger[As]=!1,this._isHovered=null,this._queueCallback(()=>{this._isWithActiveTrigger()||(this._isHovered||this._disposePopper(),this._element.removeAttribute("aria-describedby"),M.trigger(this._element,this.constructor.eventName("hidden")))},this.tip,this._isAnimated())}}update(){this._popper&&this._popper.update()}_isWithContent(){return Boolean(this._getTitle())}_getTipElement(){return this.tip||(this.tip=this._createTipElement(this._newContent||this._getContentForTemplate())),this.tip}_createTipElement(t){const e=this._getTemplateFactory(t).toHtml();if(!e)return null;e.classList.remove(_s,bs),e.classList.add(`bs-${this.constructor.NAME}-auto`);const i=(t=>{do{t+=Math.floor(1e6*Math.random())}while(document.getElementById(t));return t})(this.constructor.NAME).toString();return e.setAttribute("id",i),this._isAnimated()&&e.classList.add(_s),e}setContent(t){this._newContent=t,this._isShown()&&(this._disposePopper(),this.show())}_getTemplateFactory(t){return this._templateFactory?this._templateFactory.changeContent(t):this._templateFactory=new gs({...this._config,content:t,extraClass:this._resolvePossibleFunction(this._config.customClass)}),this._templateFactory}_getContentForTemplate(){return{[vs]:this._getTitle()}}_getTitle(){return this._resolvePossibleFunction(this._config.title)||this._element.getAttribute("data-coreui-original-title")}_initializeOnDelegatedTarget(t){return this.constructor.getOrCreateInstance(t.delegateTarget,this._getDelegateConfig())}_isAnimated(){return this._config.animation||this.tip&&this.tip.classList.contains(_s)}_isShown(){return this.tip&&this.tip.classList.contains(bs)}_createPopper(t){const e=_(this._config.placement,[this,t,this._element]),i=Ts[e.toUpperCase()];return wi(this._element,t,this._getPopperConfig(i))}_getOffset(){const{offset:t}=this._config;return"string"==typeof t?t.split(",").map(t=>Number.parseInt(t,10)):"function"==typeof t?e=>t(e,this._element):t}_resolvePossibleFunction(t){return _(t,[this._element,this._element])}_getPopperConfig(t){const e={placement:t,modifiers:[{name:"flip",options:{fallbackPlacements:this._config.fallbackPlacements}},{name:"offset",options:{offset:this._getOffset()}},{name:"preventOverflow",options:{boundary:this._config.boundary}},{name:"arrow",options:{element:`.${this.constructor.NAME}-arrow`}},{name:"preSetPlacement",enabled:!0,phase:"beforeMain",fn:t=>{this._getTipElement().setAttribute("data-popper-placement",t.state.placement)}}]};return{...e,..._(this._config.popperConfig,[void 0,e])}}_setListeners(){const t=this._config.trigger.split(" ");for(const e of t)if("click"===e)M.on(this._element,this.constructor.eventName("click"),this._config.selector,t=>{const e=this._initializeOnDelegatedTarget(t);e._activeTrigger[Cs]=!(e._isShown()&&e._activeTrigger[Cs]),e.toggle()});else if("manual"!==e){const t=e===As?this.constructor.eventName("mouseenter"):this.constructor.eventName("focusin"),i=e===As?this.constructor.eventName("mouseleave"):this.constructor.eventName("focusout");M.on(this._element,t,this._config.selector,t=>{const e=this._initializeOnDelegatedTarget(t);e._activeTrigger["focusin"===t.type?Es:As]=!0,e._enter()}),M.on(this._element,i,this._config.selector,t=>{const e=this._initializeOnDelegatedTarget(t);e._activeTrigger["focusout"===t.type?Es:As]=e._element.contains(t.relatedTarget),e._leave()})}this._hideModalHandler=()=>{this._element&&this.hide()},M.on(this._element.closest(ys),ws,this._hideModalHandler)}_fixTitle(){const t=this._element.getAttribute("title");t&&(this._element.getAttribute("aria-label")||this._element.textContent.trim()||this._element.setAttribute("aria-label",t),this._element.setAttribute("data-coreui-original-title",t),this._element.removeAttribute("title"))}_enter(){this._isShown()||this._isHovered?this._isHovered=!0:(this._isHovered=!0,this._setTimeout(()=>{this._isHovered&&this.show()},this._config.delay.show))}_leave(){this._isWithActiveTrigger()||(this._isHovered=!1,this._setTimeout(()=>{this._isHovered||this.hide()},this._config.delay.hide))}_setTimeout(t,e){clearTimeout(this._timeout),this._timeout=setTimeout(t,e)}_isWithActiveTrigger(){return Object.values(this._activeTrigger).includes(!0)}_getConfig(t){const e=H.getDataAttributes(this._element);for(const t of Object.keys(e))ms.has(t)&&delete e[t];return t={...e,..."object"==typeof t&&t?t:{}},t=this._mergeConfigObj(t),t=this._configAfterMerge(t),this._typeCheckConfig(t),t}_configAfterMerge(t){return t.container=!1===t.container?document.body:a(t.container),"number"==typeof t.delay&&(t.delay={show:t.delay,hide:t.delay}),"number"==typeof t.title&&(t.title=t.title.toString()),"number"==typeof t.content&&(t.content=t.content.toString()),t}_getDelegateConfig(){const t={};for(const[e,i]of Object.entries(this._config))this.constructor.Default[e]!==i&&(t[e]=i);return t.selector=!1,t.trigger="manual",t}_disposePopper(){this._popper&&(this._popper.destroy(),this._popper=null),this.tip&&(this.tip.remove(),this.tip=null)}static jQueryInterface(t){return this.each(function(){const e=xs.getOrCreateInstance(this,t);if("string"==typeof t){if(void 0===e[t])throw new TypeError(`No method named "${t}"`);e[t]()}})}}m(xs);const Ls=".popover-header",Ss=".popover-body",Ds={...xs.Default,content:"",offset:[0,8],placement:"right",template:'<div class="popover" role="tooltip"><div class="popover-arrow"></div><h3 class="popover-header"></h3><div class="popover-body"></div></div>',trigger:"click"},$s={...xs.DefaultType,content:"(null|string|element|function)"};class Ns extends xs{static get Default(){return Ds}static get DefaultType(){return $s}static get NAME(){return"popover"}_isWithContent(){return this._getTitle()||this._getContent()}_getContentForTemplate(){return{[Ls]:this._getTitle(),[Ss]:this._getContent()}}_getContent(){return this._resolvePossibleFunction(this._config.content)}static jQueryInterface(t){return this.each(function(){const e=Ns.getOrCreateInstance(this,t);if("string"==typeof t){if(void 0===e[t])throw new TypeError(`No method named "${t}"`);e[t]()}})}}m(Ns);const Is=".coreui.scrollspy",Ms=`activate${Is}`,Ps=`click${Is}`,js=`load${Is}.data-api`,Fs="active",Hs="[href]",Bs=".nav-link",Ws=`${Bs}, .nav-item > ${Bs}, .list-group-item`,zs={offset:null,rootMargin:"0px 0px -25%",smoothScroll:!1,target:null,threshold:[.1,.5,1]},qs={offset:"(number|null)",rootMargin:"string",smoothScroll:"boolean",target:"element",threshold:"array"};class Rs extends W{constructor(t,e){super(t,e),this._targetLinks=new Map,this._observableSections=new Map,this._rootElement="visible"===getComputedStyle(this._element).overflowY?null:this._element,this._activeTarget=null,this._observer=null,this._previousScrollData={visibleEntryTop:0,parentScrollTop:0},this.refresh()}static get Default(){return zs}static get DefaultType(){return qs}static get NAME(){return"scrollspy"}refresh(){this._initializeTargetsAndObservables(),this._maybeEnableSmoothScroll(),this._observer?this._observer.disconnect():this._observer=this._getNewObserver();for(const t of this._observableSections.values())this._observer.observe(t)}dispose(){this._observer.disconnect(),super.dispose()}_configAfterMerge(t){return t.target=a(t.target)||document.body,t.rootMargin=t.offset?`${t.offset}px 0px -30%`:t.rootMargin,"string"==typeof t.threshold&&(t.threshold=t.threshold.split(",").map(t=>Number.parseFloat(t))),t}_maybeEnableSmoothScroll(){this._config.smoothScroll&&(M.off(this._config.target,Ps),M.on(this._config.target,Ps,Hs,t=>{const e=this._observableSections.get(t.target.hash);if(e){t.preventDefault();const i=this._rootElement||window,n=e.offsetTop-this._element.offsetTop;if(i.scrollTo)return void i.scrollTo({top:n,behavior:"smooth"});i.scrollTop=n}}))}_getNewObserver(){const t={root:this._rootElement,threshold:this._config.threshold,rootMargin:this._config.rootMargin};return new IntersectionObserver(t=>this._observerCallback(t),t)}_observerCallback(t){const e=t=>this._targetLinks.get(`#${t.target.id}`),i=t=>{this._previousScrollData.visibleEntryTop=t.target.offsetTop,this._process(e(t))},n=(this._rootElement||document.documentElement).scrollTop,s=n>=this._previousScrollData.parentScrollTop;this._previousScrollData.parentScrollTop=n;for(const o of t){if(!o.isIntersecting){this._activeTarget=null,this._clearActiveClass(e(o));continue}const t=o.target.offsetTop>=this._previousScrollData.visibleEntryTop;if(s&&t){if(i(o),!n)return}else s||t||i(o)}}_initializeTargetsAndObservables(){this._targetLinks=new Map,this._observableSections=new Map;const t=q.find(Hs,this._config.target);for(const e of t){if(!e.hash||c(e))continue;const t=q.findOne(decodeURI(e.hash),this._element);l(t)&&(this._targetLinks.set(decodeURI(e.hash),e),this._observableSections.set(e.hash,t))}}_process(t){this._activeTarget!==t&&(this._clearActiveClass(this._config.target),this._activeTarget=t,t.classList.add(Fs),this._activateParents(t),M.trigger(this._element,Ms,{relatedTarget:t}))}_activateParents(t){if(t.classList.contains("dropdown-item"))q.findOne(".dropdown-toggle",t.closest(".dropdown")).classList.add(Fs);else for(const e of q.parents(t,".nav, .list-group"))for(const t of q.prev(e,Ws))t.classList.add(Fs)}_clearActiveClass(t){t.classList.remove(Fs);const e=q.find(`${Hs}.${Fs}`,t);for(const t of e)t.classList.remove(Fs)}static jQueryInterface(t){return this.each(function(){const e=Rs.getOrCreateInstance(this,t);if("string"==typeof t){if(void 0===e[t]||t.startsWith("_")||"constructor"===t)throw new TypeError(`No method named "${t}"`);e[t]()}})}}M.on(window,js,()=>{for(const t of q.find('[data-coreui-spy="scroll"]'))Rs.getOrCreateInstance(t)}),m(Rs);const Vs=".coreui.sidebar",Us=".data-api",Ks={},Qs={},Ys="hide",Xs="show",Gs="sidebar-narrow",Js="sidebar-narrow-unfoldable",Zs=`hide${Vs}`,to=`hidden${Vs}`,eo=`show${Vs}`,io=`shown${Vs}`,no=`click${Vs}${Us}`,so=`load${Vs}${Us}`,oo=".sidebar";class ro extends W{constructor(t,e){super(t),this._config=this._getConfig(e),this._show=this._isVisible(),this._mobile=this._isMobile(),this._overlaid=this._isOverlaid(),this._narrow=this._isNarrow(),this._unfoldable=this._isUnfoldable(),this._backdrop=this._initializeBackDrop(),this._addEventListeners()}static get Default(){return Ks}static get DefaultType(){return Qs}static get NAME(){return"sidebar"}show(){M.trigger(this._element,eo),this._element.classList.contains(Ys)&&this._element.classList.remove(Ys),this._overlaid&&this._element.classList.add(Xs),this._isMobile()&&(this._element.classList.add(Xs),this._backdrop.show(),(new un).hide()),this._queueCallback(()=>{!0===this._isVisible()&&(this._show=!0,(this._isMobile()||this._isOverlaid())&&this._addClickOutListener(),M.trigger(this._element,io))},this._element,!0)}hide(){M.trigger(this._element,Zs),this._element.classList.contains(Xs)&&this._element.classList.remove(Xs),this._isMobile()&&(this._backdrop.hide(),(new un).reset()),this._isMobile()||this._overlaid||this._element.classList.add(Ys),this._queueCallback(()=>{!1===this._isVisible()&&(this._show=!1,(this._isMobile()||this._isOverlaid())&&this._removeClickOutListener(),M.trigger(this._element,to))},this._element,!0)}toggle(){this._isVisible()?this.hide():this.show()}narrow(){this._isMobile()||(this._addClassName(Gs),this._narrow=!0)}unfoldable(){this._isMobile()||(this._addClassName(Js),this._unfoldable=!0)}reset(){this._isMobile()||(this._narrow&&(this._element.classList.remove(Gs),this._narrow=!1),this._unfoldable&&(this._element.classList.remove(Js),this._unfoldable=!1))}toggleNarrow(){this._narrow?this.reset():this.narrow()}toggleUnfoldable(){this._unfoldable?this.reset():this.unfoldable()}_initializeBackDrop(){return new Zi({className:"sidebar-backdrop",isVisible:this._isMobile(),isAnimated:!0,rootElement:this._element.parentNode,clickCallback:()=>this.hide()})}_isMobile(){return Boolean(window.getComputedStyle(this._element,null).getPropertyValue("--cui-is-mobile"))}_isNarrow(){return this._element.classList.contains(Gs)}_isOverlaid(){return this._element.classList.contains("sidebar-overlaid")}_isUnfoldable(){return this._element.classList.contains(Js)}_isVisible(){const t=this._element.getBoundingClientRect();return t.top>=0&&t.left>=0&&Math.floor(t.bottom)<=(window.innerHeight||document.documentElement.clientHeight)&&Math.floor(t.right)<=(window.innerWidth||document.documentElement.clientWidth)}_addClassName(t){this._element.classList.add(t)}_clickOutListener(t,e){null===t.target.closest(oo)&&(t.preventDefault(),t.stopPropagation(),e.hide())}_addClickOutListener(){M.on(document,no,t=>{this._clickOutListener(t,this)})}_removeClickOutListener(){M.off(document,no)}_addEventListeners(){this._mobile&&this._show&&this._addClickOutListener(),this._overlaid&&this._show&&this._addClickOutListener(),M.on(this._element,no,"[data-coreui-toggle]",t=>{t.preventDefault();const e=H.getDataAttribute(t.target,"toggle");"narrow"===e&&this.toggleNarrow(),"unfoldable"===e&&this.toggleUnfoldable()}),M.on(this._element,no,'[data-coreui-close="sidebar"]',t=>{t.preventDefault(),this.hide()}),M.on(window,"resize",()=>{this._isMobile()&&this._isVisible()&&(this.hide(),this._backdrop=this._initializeBackDrop())})}static sidebarInterface(t,e){const i=ro.getOrCreateInstance(t,e);if("string"==typeof e){if(void 0===i[e])throw new TypeError(`No method named "${e}"`);i[e]()}}static jQueryInterface(t){return this.each(function(){ro.sidebarInterface(this,t)})}}M.on(window,so,()=>{for(const t of Array.from(document.querySelectorAll(oo)))ro.sidebarInterface(t)}),m(ro);const ao=".coreui.tab",lo=`hide${ao}`,co=`hidden${ao}`,ho=`show${ao}`,uo=`shown${ao}`,fo=`click${ao}`,po=`keydown${ao}`,go=`load${ao}`,mo="ArrowLeft",_o="ArrowRight",bo="ArrowUp",vo="ArrowDown",yo="Home",wo="End",Ao="active",Eo="fade",Co="show",To=".dropdown-toggle",Oo=`:not(${To})`,ko='[data-coreui-toggle="tab"], [data-coreui-toggle="pill"], [data-coreui-toggle="list"]',xo=`.nav-link${Oo}, .list-group-item${Oo}, [role="tab"]${Oo}, ${ko}`,Lo=`.${Ao}[data-coreui-toggle="tab"], .${Ao}[data-coreui-toggle="pill"], .${Ao}[data-coreui-toggle="list"]`;class So extends W{constructor(t){super(t),this._parent=this._element.closest('.list-group, .nav, [role="tablist"]'),this._parent&&(this._setInitialAttributes(this._parent,this._getChildren()),M.on(this._element,po,t=>this._keydown(t)))}static get NAME(){return"tab"}show(){const t=this._element;if(this._elemIsActive(t))return;const e=this._getActiveElem(),i=e?M.trigger(e,lo,{relatedTarget:t}):null;M.trigger(t,ho,{relatedTarget:e}).defaultPrevented||i&&i.defaultPrevented||(this._deactivate(e,t),this._activate(t,e))}_activate(t,e){t&&(t.classList.add(Ao),this._activate(q.getElementFromSelector(t)),this._queueCallback(()=>{"tab"===t.getAttribute("role")?(t.removeAttribute("tabindex"),t.setAttribute("aria-selected",!0),this._toggleDropDown(t,!0),M.trigger(t,uo,{relatedTarget:e})):t.classList.add(Co)},t,t.classList.contains(Eo)))}_deactivate(t,e){t&&(t.classList.remove(Ao),t.blur(),this._deactivate(q.getElementFromSelector(t)),this._queueCallback(()=>{"tab"===t.getAttribute("role")?(t.setAttribute("aria-selected",!1),t.setAttribute("tabindex","-1"),this._toggleDropDown(t,!1),M.trigger(t,co,{relatedTarget:e})):t.classList.remove(Co)},t,t.classList.contains(Eo)))}_keydown(t){if(![mo,_o,bo,vo,yo,wo].includes(t.key))return;t.stopPropagation(),t.preventDefault();const e=this._getChildren().filter(t=>!c(t));let i;if([yo,wo].includes(t.key))i=e[t.key===yo?0:e.length-1];else{const n=[_o,vo].includes(t.key);i=v(e,t.target,n,!0)}i&&(i.focus({preventScroll:!0}),So.getOrCreateInstance(i).show())}_getChildren(){return q.find(xo,this._parent)}_getActiveElem(){return this._getChildren().find(t=>this._elemIsActive(t))||null}_setInitialAttributes(t,e){this._setAttributeIfNotExists(t,"role","tablist");for(const t of e)this._setInitialAttributesOnChild(t)}_setInitialAttributesOnChild(t){t=this._getInnerElement(t);const e=this._elemIsActive(t),i=this._getOuterElement(t);t.setAttribute("aria-selected",e),i!==t&&this._setAttributeIfNotExists(i,"role","presentation"),e||t.setAttribute("tabindex","-1"),this._setAttributeIfNotExists(t,"role","tab"),this._setInitialAttributesOnTargetPanel(t)}_setInitialAttributesOnTargetPanel(t){const e=q.getElementFromSelector(t);e&&(this._setAttributeIfNotExists(e,"role","tabpanel"),t.id&&this._setAttributeIfNotExists(e,"aria-labelledby",`${t.id}`))}_toggleDropDown(t,e){const i=this._getOuterElement(t);if(!i.classList.contains("dropdown"))return;const n=(t,n)=>{const s=q.findOne(t,i);s&&s.classList.toggle(n,e)};n(To,Ao),n(".dropdown-menu",Co),i.setAttribute("aria-expanded",e)}_setAttributeIfNotExists(t,e,i){t.hasAttribute(e)||t.setAttribute(e,i)}_elemIsActive(t){return t.classList.contains(Ao)}_getInnerElement(t){return t.matches(xo)?t:q.findOne(xo,t)}_getOuterElement(t){return t.closest(".nav-item, .list-group-item")||t}static jQueryInterface(t){return this.each(function(){const e=So.getOrCreateInstance(this);if("string"==typeof t){if(void 0===e[t]||t.startsWith("_")||"constructor"===t)throw new TypeError(`No method named "${t}"`);e[t]()}})}}M.on(document,fo,ko,function(t){["A","AREA"].includes(this.tagName)&&t.preventDefault(),c(this)||So.getOrCreateInstance(this).show()}),M.on(window,go,()=>{for(const t of q.find(Lo))So.getOrCreateInstance(t)}),m(So);const Do=".coreui.toast",$o=`mouseover${Do}`,No=`mouseout${Do}`,Io=`focusin${Do}`,Mo=`focusout${Do}`,Po=`hide${Do}`,jo=`hidden${Do}`,Fo=`show${Do}`,Ho=`shown${Do}`,Bo="hide",Wo="show",zo="showing",qo={animation:"boolean",autohide:"boolean",delay:"number"},Ro={animation:!0,autohide:!0,delay:5e3};class Vo extends W{constructor(t,e){super(t,e),this._timeout=null,this._hasMouseInteraction=!1,this._hasKeyboardInteraction=!1,this._setListeners()}static get Default(){return Ro}static get DefaultType(){return qo}static get NAME(){return"toast"}show(){M.trigger(this._element,Fo).defaultPrevented||(this._clearTimeout(),this._config.animation&&this._element.classList.add("fade"),this._element.classList.remove(Bo),u(this._element),this._element.classList.add(Wo,zo),this._queueCallback(()=>{this._element.classList.remove(zo),M.trigger(this._element,Ho),this._maybeScheduleHide()},this._element,this._config.animation))}hide(){this.isShown()&&(M.trigger(this._element,Po).defaultPrevented||(this._element.classList.add(zo),this._queueCallback(()=>{this._element.classList.add(Bo),this._element.classList.remove(zo,Wo),M.trigger(this._element,jo)},this._element,this._config.animation)))}dispose(){this._clearTimeout(),this.isShown()&&this._element.classList.remove(Wo),super.dispose()}isShown(){return this._element.classList.contains(Wo)}_maybeScheduleHide(){this._config.autohide&&(this._hasMouseInteraction||this._hasKeyboardInteraction||(this._timeout=setTimeout(()=>{this.hide()},this._config.delay)))}_onInteraction(t,e){switch(t.type){case"mouseover":case"mouseout":this._hasMouseInteraction=e;break;case"focusin":case"focusout":this._hasKeyboardInteraction=e}if(e)return void this._clearTimeout();const i=t.relatedTarget;this._element===i||this._element.contains(i)||this._maybeScheduleHide()}_setListeners(){M.on(this._element,$o,t=>this._onInteraction(t,!0)),M.on(this._element,No,t=>this._onInteraction(t,!1)),M.on(this._element,Io,t=>this._onInteraction(t,!0)),M.on(this._element,Mo,t=>this._onInteraction(t,!1))}_clearTimeout(){clearTimeout(this._timeout),this._timeout=null}static jQueryInterface(t){return this.each(function(){const e=Vo.getOrCreateInstance(this,t);if("string"==typeof t){if(void 0===e[t])throw new TypeError(`No method named "${t}"`);e[t](this)}})}}return R(Vo),m(Vo),{Alert:Q,Button:X,Carousel:St,Collapse:Rt,Dropdown:Ki,Modal:Ln,Navigation:qn,OffCanvas:as,Popover:Ns,ScrollSpy:Rs,Sidebar:ro,Tab:So,Toast:Vo,Tooltip:xs}});

/**
 * simplebar - v6.3.3
 * Scrollbars, simpler.
 * https://grsmto.github.io/simplebar/
 *
 * Made by Adrien Denat from a fork by Jonathan Nicol
 * Under MIT License
 */

var SimpleBar=function(){"use strict";var t=function(e,i){return t=Object.setPrototypeOf||{__proto__:[]}instanceof Array&&function(t,e){t.__proto__=e}||function(t,e){for(var i in e)Object.prototype.hasOwnProperty.call(e,i)&&(t[i]=e[i])},t(e,i)};function e(t){var e=typeof t;return null!=t&&("object"==e||"function"==e)}var i="object"==typeof global&&global&&global.Object===Object&&global,s="object"==typeof self&&self&&self.Object===Object&&self,r=i||s||Function("return this")(),l=function(){return r.Date.now()},o=/\s/;var n=/^\s+/;function a(t){return t?t.slice(0,function(t){for(var e=t.length;e--&&o.test(t.charAt(e)););return e}(t)+1).replace(n,""):t}var c=r.Symbol,h=Object.prototype,u=h.hasOwnProperty,d=h.toString,p=c?c.toStringTag:void 0;var v=Object.prototype.toString;var f=c?c.toStringTag:void 0;function m(t){return null==t?void 0===t?"[object Undefined]":"[object Null]":f&&f in Object(t)?function(t){var e=u.call(t,p),i=t[p];try{t[p]=void 0;var s=!0}catch(t){}var r=d.call(t);return s&&(e?t[p]=i:delete t[p]),r}(t):function(t){return v.call(t)}(t)}var b=/^[-+]0x[0-9a-f]+$/i,g=/^0b[01]+$/i,x=/^0o[0-7]+$/i,y=parseInt;function E(t){if("number"==typeof t)return t;if(function(t){return"symbol"==typeof t||function(t){return null!=t&&"object"==typeof t}(t)&&"[object Symbol]"==m(t)}(t))return NaN;if(e(t)){var i="function"==typeof t.valueOf?t.valueOf():t;t=e(i)?i+"":i}if("string"!=typeof t)return 0===t?t:+t;t=a(t);var s=g.test(t);return s||x.test(t)?y(t.slice(2),s?2:8):b.test(t)?NaN:+t}var O=Math.max,w=Math.min;function S(t,i,s){var r,o,n,a,c,h,u=0,d=!1,p=!1,v=!0;if("function"!=typeof t)throw new TypeError("Expected a function");function f(e){var i=r,s=o;return r=o=void 0,u=e,a=t.apply(s,i)}function m(t){return u=t,c=setTimeout(g,i),d?f(t):a}function b(t){var e=t-h;return void 0===h||e>=i||e<0||p&&t-u>=n}function g(){var t=l();if(b(t))return x(t);c=setTimeout(g,function(t){var e=i-(t-h);return p?w(e,n-(t-u)):e}(t))}function x(t){return c=void 0,v&&r?f(t):(r=o=void 0,a)}function y(){var t=l(),e=b(t);if(r=arguments,o=this,h=t,e){if(void 0===c)return m(h);if(p)return clearTimeout(c),c=setTimeout(g,i),f(h)}return void 0===c&&(c=setTimeout(g,i)),a}return i=E(i)||0,e(s)&&(d=!!s.leading,n=(p="maxWait"in s)?O(E(s.maxWait)||0,i):n,v="trailing"in s?!!s.trailing:v),y.cancel=function(){void 0!==c&&clearTimeout(c),u=0,r=h=o=c=void 0},y.flush=function(){return void 0===c?a:x(l())},y}var A=function(){return A=Object.assign||function(t){for(var e,i=1,s=arguments.length;i<s;i++)for(var r in e=arguments[i])Object.prototype.hasOwnProperty.call(e,r)&&(t[r]=e[r]);return t},A.apply(this,arguments)};function k(t){return t&&t.ownerDocument&&t.ownerDocument.defaultView?t.ownerDocument.defaultView:window}function W(t){return t&&t.ownerDocument?t.ownerDocument:document}var M=function(t){return Array.prototype.reduce.call(t,(function(t,e){var i=e.name.match(/data-simplebar-(.+)/);if(i){var s=i[1].replace(/\W+(.)/g,(function(t,e){return e.toUpperCase()}));switch(e.value){case"true":t[s]=!0;break;case"false":t[s]=!1;break;case void 0:t[s]=!0;break;default:t[s]=e.value}}return t}),{})};function N(t,e){var i;t&&(i=t.classList).add.apply(i,e.split(" "))}function L(t,e){t&&e.split(" ").forEach((function(e){t.classList.remove(e)}))}function z(t){return".".concat(t.split(" ").join("."))}var C=!("undefined"==typeof window||!window.document||!window.document.createElement),T=Object.freeze({__proto__:null,addClasses:N,canUseDOM:C,classNamesToQuery:z,getElementDocument:W,getElementWindow:k,getOptions:M,removeClasses:L}),D=null,R=null;function V(){if(null===D){if("undefined"==typeof document)return D=0;var t=document.body,e=document.createElement("div");e.classList.add("simplebar-hide-scrollbar"),t.appendChild(e);var i=e.getBoundingClientRect().right;t.removeChild(e),D=i}return D}C&&window.addEventListener("resize",(function(){R!==window.devicePixelRatio&&(R=window.devicePixelRatio,D=null)}));var H=k,j=W,B=M,_=N,q=L,P=z,X=function(){function t(i,s){void 0===s&&(s={});var r=this;if(this.removePreventClickId=null,this.minScrollbarWidth=20,this.stopScrollDelay=175,this.isScrolling=!1,this.isMouseEntering=!1,this.isDragging=!1,this.scrollXTicking=!1,this.scrollYTicking=!1,this.wrapperEl=null,this.contentWrapperEl=null,this.contentEl=null,this.offsetEl=null,this.maskEl=null,this.placeholderEl=null,this.heightAutoObserverWrapperEl=null,this.heightAutoObserverEl=null,this.rtlHelpers=null,this.scrollbarWidth=0,this.resizeObserver=null,this.mutationObserver=null,this.elStyles=null,this.isRtl=null,this.mouseX=0,this.mouseY=0,this.onMouseMove=function(){},this.onWindowResize=function(){},this.onStopScrolling=function(){},this.onMouseEntered=function(){},this.onScroll=function(){var t=H(r.el);r.scrollXTicking||(t.requestAnimationFrame(r.scrollX),r.scrollXTicking=!0),r.scrollYTicking||(t.requestAnimationFrame(r.scrollY),r.scrollYTicking=!0),r.isScrolling||(r.isScrolling=!0,_(r.el,r.classNames.scrolling)),r.showScrollbar("x"),r.showScrollbar("y"),r.onStopScrolling()},this.scrollX=function(){r.axis.x.isOverflowing&&r.positionScrollbar("x"),r.scrollXTicking=!1},this.scrollY=function(){r.axis.y.isOverflowing&&r.positionScrollbar("y"),r.scrollYTicking=!1},this._onStopScrolling=function(){q(r.el,r.classNames.scrolling),r.options.autoHide&&(r.hideScrollbar("x"),r.hideScrollbar("y")),r.isScrolling=!1},this.onMouseEnter=function(){r.isMouseEntering||(_(r.el,r.classNames.mouseEntered),r.showScrollbar("x"),r.showScrollbar("y"),r.isMouseEntering=!0),r.onMouseEntered()},this._onMouseEntered=function(){q(r.el,r.classNames.mouseEntered),r.options.autoHide&&(r.hideScrollbar("x"),r.hideScrollbar("y")),r.isMouseEntering=!1},this._onMouseMove=function(t){r.mouseX=t.clientX,r.mouseY=t.clientY,(r.axis.x.isOverflowing||r.axis.x.forceVisible)&&r.onMouseMoveForAxis("x"),(r.axis.y.isOverflowing||r.axis.y.forceVisible)&&r.onMouseMoveForAxis("y")},this.onMouseLeave=function(){r.onMouseMove.cancel(),(r.axis.x.isOverflowing||r.axis.x.forceVisible)&&r.onMouseLeaveForAxis("x"),(r.axis.y.isOverflowing||r.axis.y.forceVisible)&&r.onMouseLeaveForAxis("y"),r.mouseX=-1,r.mouseY=-1},this._onWindowResize=function(){r.scrollbarWidth=r.getScrollbarWidth(),r.hideNativeScrollbar()},this.onPointerEvent=function(t){var e,i;r.axis.x.track.el&&r.axis.y.track.el&&r.axis.x.scrollbar.el&&r.axis.y.scrollbar.el&&(r.axis.x.track.rect=r.axis.x.track.el.getBoundingClientRect(),r.axis.y.track.rect=r.axis.y.track.el.getBoundingClientRect(),(r.axis.x.isOverflowing||r.axis.x.forceVisible)&&(e=r.isWithinBounds(r.axis.x.track.rect)),(r.axis.y.isOverflowing||r.axis.y.forceVisible)&&(i=r.isWithinBounds(r.axis.y.track.rect)),(e||i)&&(t.stopPropagation(),"pointerdown"===t.type&&"touch"!==t.pointerType&&(e&&(r.axis.x.scrollbar.rect=r.axis.x.scrollbar.el.getBoundingClientRect(),r.isWithinBounds(r.axis.x.scrollbar.rect)?r.onDragStart(t,"x"):r.onTrackClick(t,"x")),i&&(r.axis.y.scrollbar.rect=r.axis.y.scrollbar.el.getBoundingClientRect(),r.isWithinBounds(r.axis.y.scrollbar.rect)?r.onDragStart(t,"y"):r.onTrackClick(t,"y")))))},this.drag=function(e){var i,s,l,o,n,a,c,h,u,d,p;if(r.draggedAxis&&r.contentWrapperEl){var v=r.axis[r.draggedAxis].track,f=null!==(s=null===(i=v.rect)||void 0===i?void 0:i[r.axis[r.draggedAxis].sizeAttr])&&void 0!==s?s:0,m=r.axis[r.draggedAxis].scrollbar,b=null!==(o=null===(l=r.contentWrapperEl)||void 0===l?void 0:l[r.axis[r.draggedAxis].scrollSizeAttr])&&void 0!==o?o:0,g=parseInt(null!==(a=null===(n=r.elStyles)||void 0===n?void 0:n[r.axis[r.draggedAxis].sizeAttr])&&void 0!==a?a:"0px",10);e.preventDefault(),e.stopPropagation();var x=("y"===r.draggedAxis?e.pageY:e.pageX)-(null!==(h=null===(c=v.rect)||void 0===c?void 0:c[r.axis[r.draggedAxis].offsetAttr])&&void 0!==h?h:0)-r.axis[r.draggedAxis].dragOffset,y=(x="x"===r.draggedAxis&&r.isRtl?(null!==(d=null===(u=v.rect)||void 0===u?void 0:u[r.axis[r.draggedAxis].sizeAttr])&&void 0!==d?d:0)-m.size-x:x)/(f-m.size)*(b-g);"x"===r.draggedAxis&&r.isRtl&&(y=(null===(p=t.getRtlHelpers())||void 0===p?void 0:p.isScrollingToNegative)?-y:y),r.contentWrapperEl[r.axis[r.draggedAxis].scrollOffsetAttr]=y}},this.onEndDrag=function(t){r.isDragging=!1;var e=j(r.el),i=H(r.el);t.preventDefault(),t.stopPropagation(),q(r.el,r.classNames.dragging),r.onStopScrolling(),e.removeEventListener("mousemove",r.drag,!0),e.removeEventListener("mouseup",r.onEndDrag,!0),r.removePreventClickId=i.setTimeout((function(){e.removeEventListener("click",r.preventClick,!0),e.removeEventListener("dblclick",r.preventClick,!0),r.removePreventClickId=null}))},this.preventClick=function(t){t.preventDefault(),t.stopPropagation()},this.el=i,this.options=A(A({},t.defaultOptions),s),this.classNames=A(A({},t.defaultOptions.classNames),s.classNames),this.axis={x:{scrollOffsetAttr:"scrollLeft",sizeAttr:"width",scrollSizeAttr:"scrollWidth",offsetSizeAttr:"offsetWidth",offsetAttr:"left",overflowAttr:"overflowX",dragOffset:0,isOverflowing:!0,forceVisible:!1,track:{size:null,el:null,rect:null,isVisible:!1},scrollbar:{size:null,el:null,rect:null,isVisible:!1}},y:{scrollOffsetAttr:"scrollTop",sizeAttr:"height",scrollSizeAttr:"scrollHeight",offsetSizeAttr:"offsetHeight",offsetAttr:"top",overflowAttr:"overflowY",dragOffset:0,isOverflowing:!0,forceVisible:!1,track:{size:null,el:null,rect:null,isVisible:!1},scrollbar:{size:null,el:null,rect:null,isVisible:!1}}},"object"!=typeof this.el||!this.el.nodeName)throw new Error("Argument passed to SimpleBar must be an HTML element instead of ".concat(this.el));this.onMouseMove=function(t,i,s){var r=!0,l=!0;if("function"!=typeof t)throw new TypeError("Expected a function");return e(s)&&(r="leading"in s?!!s.leading:r,l="trailing"in s?!!s.trailing:l),S(t,i,{leading:r,maxWait:i,trailing:l})}(this._onMouseMove,64),this.onWindowResize=S(this._onWindowResize,64,{leading:!0}),this.onStopScrolling=S(this._onStopScrolling,this.stopScrollDelay),this.onMouseEntered=S(this._onMouseEntered,this.stopScrollDelay),this.init()}return t.getRtlHelpers=function(){if(t.rtlHelpers)return t.rtlHelpers;var e=document.createElement("div");e.innerHTML='<div class="simplebar-dummy-scrollbar-size"><div></div></div>';var i=e.firstElementChild,s=null==i?void 0:i.firstElementChild;if(!s)return null;document.body.appendChild(i),i.scrollLeft=0;var r=t.getOffset(i),l=t.getOffset(s);i.scrollLeft=-999;var o=t.getOffset(s);return document.body.removeChild(i),t.rtlHelpers={isScrollOriginAtZero:r.left!==l.left,isScrollingToNegative:l.left!==o.left},t.rtlHelpers},t.prototype.getScrollbarWidth=function(){try{return this.contentWrapperEl&&"none"===getComputedStyle(this.contentWrapperEl,"::-webkit-scrollbar").display||"scrollbarWidth"in document.documentElement.style||"-ms-overflow-style"in document.documentElement.style?0:V()}catch(t){return V()}},t.getOffset=function(t){var e=t.getBoundingClientRect(),i=j(t),s=H(t);return{top:e.top+(s.pageYOffset||i.documentElement.scrollTop),left:e.left+(s.pageXOffset||i.documentElement.scrollLeft)}},t.prototype.init=function(){C&&(this.initDOM(),this.rtlHelpers=t.getRtlHelpers(),this.scrollbarWidth=this.getScrollbarWidth(),this.recalculate(),this.initListeners())},t.prototype.initDOM=function(){var t,e;this.wrapperEl=this.el.querySelector(P(this.classNames.wrapper)),this.contentWrapperEl=this.options.scrollableNode||this.el.querySelector(P(this.classNames.contentWrapper)),this.contentEl=this.options.contentNode||this.el.querySelector(P(this.classNames.contentEl)),this.offsetEl=this.el.querySelector(P(this.classNames.offset)),this.maskEl=this.el.querySelector(P(this.classNames.mask)),this.placeholderEl=this.findChild(this.wrapperEl,P(this.classNames.placeholder)),this.heightAutoObserverWrapperEl=this.el.querySelector(P(this.classNames.heightAutoObserverWrapperEl)),this.heightAutoObserverEl=this.el.querySelector(P(this.classNames.heightAutoObserverEl)),this.axis.x.track.el=this.findChild(this.el,"".concat(P(this.classNames.track)).concat(P(this.classNames.horizontal))),this.axis.y.track.el=this.findChild(this.el,"".concat(P(this.classNames.track)).concat(P(this.classNames.vertical))),this.axis.x.scrollbar.el=(null===(t=this.axis.x.track.el)||void 0===t?void 0:t.querySelector(P(this.classNames.scrollbar)))||null,this.axis.y.scrollbar.el=(null===(e=this.axis.y.track.el)||void 0===e?void 0:e.querySelector(P(this.classNames.scrollbar)))||null,this.options.autoHide||(_(this.axis.x.scrollbar.el,this.classNames.visible),_(this.axis.y.scrollbar.el,this.classNames.visible))},t.prototype.initListeners=function(){var t,e=this,i=H(this.el);if(this.el.addEventListener("mouseenter",this.onMouseEnter),this.el.addEventListener("pointerdown",this.onPointerEvent,!0),this.el.addEventListener("mousemove",this.onMouseMove),this.el.addEventListener("mouseleave",this.onMouseLeave),null===(t=this.contentWrapperEl)||void 0===t||t.addEventListener("scroll",this.onScroll),i.addEventListener("resize",this.onWindowResize),this.contentEl){if(window.ResizeObserver){var s=!1,r=i.ResizeObserver||ResizeObserver;this.resizeObserver=new r((function(){s&&i.requestAnimationFrame((function(){e.recalculate()}))})),this.resizeObserver.observe(this.el),this.resizeObserver.observe(this.contentEl),i.requestAnimationFrame((function(){s=!0}))}this.mutationObserver=new i.MutationObserver((function(){i.requestAnimationFrame((function(){e.recalculate()}))})),this.mutationObserver.observe(this.contentEl,{childList:!0,subtree:!0,characterData:!0})}},t.prototype.recalculate=function(){if(this.heightAutoObserverEl&&this.contentEl&&this.contentWrapperEl&&this.wrapperEl&&this.placeholderEl){var t=H(this.el);this.elStyles=t.getComputedStyle(this.el),this.isRtl="rtl"===this.elStyles.direction;var e=this.contentEl.offsetWidth,i=this.heightAutoObserverEl.offsetHeight<=1,s=this.heightAutoObserverEl.offsetWidth<=1||e>0,r=this.contentWrapperEl.offsetWidth,l=this.elStyles.overflowX,o=this.elStyles.overflowY;this.contentEl.style.padding="".concat(this.elStyles.paddingTop," ").concat(this.elStyles.paddingRight," ").concat(this.elStyles.paddingBottom," ").concat(this.elStyles.paddingLeft),this.wrapperEl.style.margin="-".concat(this.elStyles.paddingTop," -").concat(this.elStyles.paddingRight," -").concat(this.elStyles.paddingBottom," -").concat(this.elStyles.paddingLeft);var n=this.contentEl.scrollHeight,a=this.contentEl.scrollWidth;this.contentWrapperEl.style.height=i?"auto":"100%",this.placeholderEl.style.width=s?"".concat(e||a,"px"):"auto",this.placeholderEl.style.height="".concat(n,"px");var c=this.contentWrapperEl.offsetHeight;this.axis.x.isOverflowing=0!==e&&a>e,this.axis.y.isOverflowing=n>c,this.axis.x.isOverflowing="hidden"!==l&&this.axis.x.isOverflowing,this.axis.y.isOverflowing="hidden"!==o&&this.axis.y.isOverflowing,this.axis.x.forceVisible="x"===this.options.forceVisible||!0===this.options.forceVisible,this.axis.y.forceVisible="y"===this.options.forceVisible||!0===this.options.forceVisible,this.hideNativeScrollbar();var h=this.axis.x.isOverflowing?this.scrollbarWidth:0,u=this.axis.y.isOverflowing?this.scrollbarWidth:0;this.axis.x.isOverflowing=this.axis.x.isOverflowing&&a>r-u,this.axis.y.isOverflowing=this.axis.y.isOverflowing&&n>c-h,this.axis.x.scrollbar.size=this.getScrollbarSize("x"),this.axis.y.scrollbar.size=this.getScrollbarSize("y"),this.axis.x.scrollbar.el&&(this.axis.x.scrollbar.el.style.width="".concat(this.axis.x.scrollbar.size,"px")),this.axis.y.scrollbar.el&&(this.axis.y.scrollbar.el.style.height="".concat(this.axis.y.scrollbar.size,"px")),this.positionScrollbar("x"),this.positionScrollbar("y"),this.toggleTrackVisibility("x"),this.toggleTrackVisibility("y")}},t.prototype.getScrollbarSize=function(t){var e,i;if(void 0===t&&(t="y"),!this.axis[t].isOverflowing||!this.contentEl)return 0;var s,r=this.contentEl[this.axis[t].scrollSizeAttr],l=null!==(i=null===(e=this.axis[t].track.el)||void 0===e?void 0:e[this.axis[t].offsetSizeAttr])&&void 0!==i?i:0,o=l/r;return s=Math.max(~~(o*l),this.options.scrollbarMinSize),this.options.scrollbarMaxSize&&(s=Math.min(s,this.options.scrollbarMaxSize)),s},t.prototype.positionScrollbar=function(e){var i,s,r;void 0===e&&(e="y");var l=this.axis[e].scrollbar;if(this.axis[e].isOverflowing&&this.contentWrapperEl&&l.el&&this.elStyles){var o=this.contentWrapperEl[this.axis[e].scrollSizeAttr],n=(null===(i=this.axis[e].track.el)||void 0===i?void 0:i[this.axis[e].offsetSizeAttr])||0,a=parseInt(this.elStyles[this.axis[e].sizeAttr],10),c=this.contentWrapperEl[this.axis[e].scrollOffsetAttr];c="x"===e&&this.isRtl&&(null===(s=t.getRtlHelpers())||void 0===s?void 0:s.isScrollOriginAtZero)?-c:c,"x"===e&&this.isRtl&&(c=(null===(r=t.getRtlHelpers())||void 0===r?void 0:r.isScrollingToNegative)?c:-c);var h=c/(o-a),u=~~((n-l.size)*h);u="x"===e&&this.isRtl?-u+(n-l.size):u,l.el.style.transform="x"===e?"translate3d(".concat(u,"px, 0, 0)"):"translate3d(0, ".concat(u,"px, 0)")}},t.prototype.toggleTrackVisibility=function(t){void 0===t&&(t="y");var e=this.axis[t].track.el,i=this.axis[t].scrollbar.el;e&&i&&this.contentWrapperEl&&(this.axis[t].isOverflowing||this.axis[t].forceVisible?(e.style.visibility="visible",this.contentWrapperEl.style[this.axis[t].overflowAttr]="scroll",this.el.classList.add("".concat(this.classNames.scrollable,"-").concat(t))):(e.style.visibility="hidden",this.contentWrapperEl.style[this.axis[t].overflowAttr]="hidden",this.el.classList.remove("".concat(this.classNames.scrollable,"-").concat(t))),this.axis[t].isOverflowing?i.style.display="block":i.style.display="none")},t.prototype.showScrollbar=function(t){void 0===t&&(t="y"),this.axis[t].isOverflowing&&!this.axis[t].scrollbar.isVisible&&(_(this.axis[t].scrollbar.el,this.classNames.visible),this.axis[t].scrollbar.isVisible=!0)},t.prototype.hideScrollbar=function(t){void 0===t&&(t="y"),this.isDragging||this.axis[t].isOverflowing&&this.axis[t].scrollbar.isVisible&&(q(this.axis[t].scrollbar.el,this.classNames.visible),this.axis[t].scrollbar.isVisible=!1)},t.prototype.hideNativeScrollbar=function(){this.offsetEl&&(this.offsetEl.style[this.isRtl?"left":"right"]=this.axis.y.isOverflowing||this.axis.y.forceVisible?"-".concat(this.scrollbarWidth,"px"):"0px",this.offsetEl.style.bottom=this.axis.x.isOverflowing||this.axis.x.forceVisible?"-".concat(this.scrollbarWidth,"px"):"0px")},t.prototype.onMouseMoveForAxis=function(t){void 0===t&&(t="y");var e=this.axis[t];e.track.el&&e.scrollbar.el&&(e.track.rect=e.track.el.getBoundingClientRect(),e.scrollbar.rect=e.scrollbar.el.getBoundingClientRect(),this.isWithinBounds(e.track.rect)?(this.showScrollbar(t),_(e.track.el,this.classNames.hover),this.isWithinBounds(e.scrollbar.rect)?_(e.scrollbar.el,this.classNames.hover):q(e.scrollbar.el,this.classNames.hover)):(q(e.track.el,this.classNames.hover),this.options.autoHide&&this.hideScrollbar(t)))},t.prototype.onMouseLeaveForAxis=function(t){void 0===t&&(t="y"),q(this.axis[t].track.el,this.classNames.hover),q(this.axis[t].scrollbar.el,this.classNames.hover),this.options.autoHide&&this.hideScrollbar(t)},t.prototype.onDragStart=function(t,e){var i;void 0===e&&(e="y"),this.isDragging=!0;var s=j(this.el),r=H(this.el),l=this.axis[e].scrollbar,o="y"===e?t.pageY:t.pageX;this.axis[e].dragOffset=o-((null===(i=l.rect)||void 0===i?void 0:i[this.axis[e].offsetAttr])||0),this.draggedAxis=e,_(this.el,this.classNames.dragging),s.addEventListener("mousemove",this.drag,!0),s.addEventListener("mouseup",this.onEndDrag,!0),null===this.removePreventClickId?(s.addEventListener("click",this.preventClick,!0),s.addEventListener("dblclick",this.preventClick,!0)):(r.clearTimeout(this.removePreventClickId),this.removePreventClickId=null)},t.prototype.onTrackClick=function(t,e){var i,s,r,l,o=this;void 0===e&&(e="y");var n=this.axis[e];if(this.options.clickOnTrack&&n.scrollbar.el&&this.contentWrapperEl){t.preventDefault();var a=H(this.el);this.axis[e].scrollbar.rect=n.scrollbar.el.getBoundingClientRect();var c=null!==(s=null===(i=this.axis[e].scrollbar.rect)||void 0===i?void 0:i[this.axis[e].offsetAttr])&&void 0!==s?s:0,h=parseInt(null!==(l=null===(r=this.elStyles)||void 0===r?void 0:r[this.axis[e].sizeAttr])&&void 0!==l?l:"0px",10),u=this.contentWrapperEl[this.axis[e].scrollOffsetAttr],d=("y"===e?this.mouseY-c:this.mouseX-c)<0?-1:1,p=-1===d?u-h:u+h,v=function(){o.contentWrapperEl&&(-1===d?u>p&&(u-=40,o.contentWrapperEl[o.axis[e].scrollOffsetAttr]=u,a.requestAnimationFrame(v)):u<p&&(u+=40,o.contentWrapperEl[o.axis[e].scrollOffsetAttr]=u,a.requestAnimationFrame(v)))};v()}},t.prototype.getContentElement=function(){return this.contentEl},t.prototype.getScrollElement=function(){return this.contentWrapperEl},t.prototype.removeListeners=function(){var t=H(this.el);this.el.removeEventListener("mouseenter",this.onMouseEnter),this.el.removeEventListener("pointerdown",this.onPointerEvent,!0),this.el.removeEventListener("mousemove",this.onMouseMove),this.el.removeEventListener("mouseleave",this.onMouseLeave),this.contentWrapperEl&&this.contentWrapperEl.removeEventListener("scroll",this.onScroll),t.removeEventListener("resize",this.onWindowResize),this.mutationObserver&&this.mutationObserver.disconnect(),this.resizeObserver&&this.resizeObserver.disconnect(),this.onMouseMove.cancel(),this.onWindowResize.cancel(),this.onStopScrolling.cancel(),this.onMouseEntered.cancel()},t.prototype.unMount=function(){this.removeListeners()},t.prototype.isWithinBounds=function(t){return this.mouseX>=t.left&&this.mouseX<=t.left+t.width&&this.mouseY>=t.top&&this.mouseY<=t.top+t.height},t.prototype.findChild=function(t,e){var i=t.matches||t.webkitMatchesSelector||t.mozMatchesSelector||t.msMatchesSelector;return Array.prototype.filter.call(t.children,(function(t){return i.call(t,e)}))[0]},t.rtlHelpers=null,t.defaultOptions={forceVisible:!1,clickOnTrack:!0,scrollbarMinSize:25,scrollbarMaxSize:0,ariaLabel:"scrollable content",tabIndex:0,classNames:{contentEl:"simplebar-content",contentWrapper:"simplebar-content-wrapper",offset:"simplebar-offset",mask:"simplebar-mask",wrapper:"simplebar-wrapper",placeholder:"simplebar-placeholder",scrollbar:"simplebar-scrollbar",track:"simplebar-track",heightAutoObserverWrapperEl:"simplebar-height-auto-observer-wrapper",heightAutoObserverEl:"simplebar-height-auto-observer",visible:"simplebar-visible",horizontal:"simplebar-horizontal",vertical:"simplebar-vertical",hover:"simplebar-hover",dragging:"simplebar-dragging",scrolling:"simplebar-scrolling",scrollable:"simplebar-scrollable",mouseEntered:"simplebar-mouse-entered"},scrollableNode:null,contentNode:null,autoHide:!0},t.getOptions=B,t.helpers=T,t}(),Y=X.helpers,F=Y.getOptions,I=Y.addClasses,U=Y.canUseDOM,$=function(e){function i(){for(var t=[],s=0;s<arguments.length;s++)t[s]=arguments[s];var r=e.apply(this,t)||this;return i.instances.set(t[0],r),r}return function(e,i){if("function"!=typeof i&&null!==i)throw new TypeError("Class extends value "+String(i)+" is not a constructor or null");function s(){this.constructor=e}t(e,i),e.prototype=null===i?Object.create(i):(s.prototype=i.prototype,new s)}(i,e),i.initDOMLoadedElements=function(){document.removeEventListener("DOMContentLoaded",this.initDOMLoadedElements),window.removeEventListener("load",this.initDOMLoadedElements),Array.prototype.forEach.call(document.querySelectorAll("[data-simplebar]"),(function(t){"init"===t.getAttribute("data-simplebar")||i.instances.has(t)||new i(t,F(t.attributes))}))},i.removeObserver=function(){var t;null===(t=i.globalObserver)||void 0===t||t.disconnect()},i.prototype.initDOM=function(){var t,e,i,s=this;if(!Array.prototype.filter.call(this.el.children,(function(t){return t.classList.contains(s.classNames.wrapper)})).length){for(this.wrapperEl=document.createElement("div"),this.contentWrapperEl=document.createElement("div"),this.offsetEl=document.createElement("div"),this.maskEl=document.createElement("div"),this.contentEl=document.createElement("div"),this.placeholderEl=document.createElement("div"),this.heightAutoObserverWrapperEl=document.createElement("div"),this.heightAutoObserverEl=document.createElement("div"),I(this.wrapperEl,this.classNames.wrapper),I(this.contentWrapperEl,this.classNames.contentWrapper),I(this.offsetEl,this.classNames.offset),I(this.maskEl,this.classNames.mask),I(this.contentEl,this.classNames.contentEl),I(this.placeholderEl,this.classNames.placeholder),I(this.heightAutoObserverWrapperEl,this.classNames.heightAutoObserverWrapperEl),I(this.heightAutoObserverEl,this.classNames.heightAutoObserverEl);this.el.firstChild;)this.contentEl.appendChild(this.el.firstChild);this.contentWrapperEl.appendChild(this.contentEl),this.offsetEl.appendChild(this.contentWrapperEl),this.maskEl.appendChild(this.offsetEl),this.heightAutoObserverWrapperEl.appendChild(this.heightAutoObserverEl),this.wrapperEl.appendChild(this.heightAutoObserverWrapperEl),this.wrapperEl.appendChild(this.maskEl),this.wrapperEl.appendChild(this.placeholderEl),this.el.appendChild(this.wrapperEl),null===(t=this.contentWrapperEl)||void 0===t||t.setAttribute("tabindex",this.options.tabIndex.toString()),null===(e=this.contentWrapperEl)||void 0===e||e.setAttribute("role","region"),null===(i=this.contentWrapperEl)||void 0===i||i.setAttribute("aria-label",this.options.ariaLabel)}if(!this.axis.x.track.el||!this.axis.y.track.el){var r=document.createElement("div"),l=document.createElement("div");I(r,this.classNames.track),I(l,this.classNames.scrollbar),r.appendChild(l),this.axis.x.track.el=r.cloneNode(!0),I(this.axis.x.track.el,this.classNames.horizontal),this.axis.y.track.el=r.cloneNode(!0),I(this.axis.y.track.el,this.classNames.vertical),this.el.appendChild(this.axis.x.track.el),this.el.appendChild(this.axis.y.track.el)}X.prototype.initDOM.call(this),this.el.setAttribute("data-simplebar","init")},i.prototype.unMount=function(){X.prototype.unMount.call(this),i.instances.delete(this.el)},i.initHtmlApi=function(){this.initDOMLoadedElements=this.initDOMLoadedElements.bind(this),"undefined"!=typeof MutationObserver&&(this.globalObserver=new MutationObserver(i.handleMutations),this.globalObserver.observe(document,{childList:!0,subtree:!0})),"complete"===document.readyState||"loading"!==document.readyState&&!document.documentElement.doScroll?window.setTimeout(this.initDOMLoadedElements):(document.addEventListener("DOMContentLoaded",this.initDOMLoadedElements),window.addEventListener("load",this.initDOMLoadedElements))},i.handleMutations=function(t){t.forEach((function(t){t.addedNodes.forEach((function(t){1===t.nodeType&&(t.hasAttribute("data-simplebar")?!i.instances.has(t)&&document.documentElement.contains(t)&&new i(t,F(t.attributes)):t.querySelectorAll("[data-simplebar]").forEach((function(t){"init"!==t.getAttribute("data-simplebar")&&!i.instances.has(t)&&document.documentElement.contains(t)&&new i(t,F(t.attributes))})))})),t.removedNodes.forEach((function(t){var e;1===t.nodeType&&("init"===t.getAttribute("data-simplebar")?!document.documentElement.contains(t)&&(null===(e=i.instances.get(t))||void 0===e||e.unMount()):Array.prototype.forEach.call(t.querySelectorAll('[data-simplebar="init"]'),(function(t){var e;!document.documentElement.contains(t)&&(null===(e=i.instances.get(t))||void 0===e||e.unMount())})))}))}))},i.instances=new WeakMap,i}(X);return U&&$.initHtmlApi(),$}();

/*!
 * Chart.js v4.5.1
 * https://www.chartjs.org
 * (c) 2025 Chart.js Contributors
 * Released under the MIT License
 */
!function(t,e){"object"==typeof exports&&"undefined"!=typeof module?module.exports=e():"function"==typeof define&&define.amd?define(e):(t="undefined"!=typeof globalThis?globalThis:t||self).Chart=e()}(this,(function(){"use strict";var t=Object.freeze({__proto__:null,get Colors(){return Jo},get Decimation(){return ta},get Filler(){return ba},get Legend(){return Ma},get SubTitle(){return Pa},get Title(){return ka},get Tooltip(){return Na}});function e(){}const i=(()=>{let t=0;return()=>t++})();function s(t){return null==t}function n(t){if(Array.isArray&&Array.isArray(t))return!0;const e=Object.prototype.toString.call(t);return"[object"===e.slice(0,7)&&"Array]"===e.slice(-6)}function o(t){return null!==t&&"[object Object]"===Object.prototype.toString.call(t)}function a(t){return("number"==typeof t||t instanceof Number)&&isFinite(+t)}function r(t,e){return a(t)?t:e}function l(t,e){return void 0===t?e:t}const h=(t,e)=>"string"==typeof t&&t.endsWith("%")?parseFloat(t)/100:+t/e,c=(t,e)=>"string"==typeof t&&t.endsWith("%")?parseFloat(t)/100*e:+t;function d(t,e,i){if(t&&"function"==typeof t.call)return t.apply(i,e)}function u(t,e,i,s){let a,r,l;if(n(t))if(r=t.length,s)for(a=r-1;a>=0;a--)e.call(i,t[a],a);else for(a=0;a<r;a++)e.call(i,t[a],a);else if(o(t))for(l=Object.keys(t),r=l.length,a=0;a<r;a++)e.call(i,t[l[a]],l[a])}function f(t,e){let i,s,n,o;if(!t||!e||t.length!==e.length)return!1;for(i=0,s=t.length;i<s;++i)if(n=t[i],o=e[i],n.datasetIndex!==o.datasetIndex||n.index!==o.index)return!1;return!0}function g(t){if(n(t))return t.map(g);if(o(t)){const e=Object.create(null),i=Object.keys(t),s=i.length;let n=0;for(;n<s;++n)e[i[n]]=g(t[i[n]]);return e}return t}function p(t){return-1===["__proto__","prototype","constructor"].indexOf(t)}function m(t,e,i,s){if(!p(t))return;const n=e[t],a=i[t];o(n)&&o(a)?x(n,a,s):e[t]=g(a)}function x(t,e,i){const s=n(e)?e:[e],a=s.length;if(!o(t))return t;const r=(i=i||{}).merger||m;let l;for(let e=0;e<a;++e){if(l=s[e],!o(l))continue;const n=Object.keys(l);for(let e=0,s=n.length;e<s;++e)r(n[e],t,l,i)}return t}function b(t,e){return x(t,e,{merger:_})}function _(t,e,i){if(!p(t))return;const s=e[t],n=i[t];o(s)&&o(n)?b(s,n):Object.prototype.hasOwnProperty.call(e,t)||(e[t]=g(n))}const y={"":t=>t,x:t=>t.x,y:t=>t.y};function v(t){const e=t.split("."),i=[];let s="";for(const t of e)s+=t,s.endsWith("\\")?s=s.slice(0,-1)+".":(i.push(s),s="");return i}function M(t,e){const i=y[e]||(y[e]=function(t){const e=v(t);return t=>{for(const i of e){if(""===i)break;t=t&&t[i]}return t}}(e));return i(t)}function w(t){return t.charAt(0).toUpperCase()+t.slice(1)}const k=t=>void 0!==t,S=t=>"function"==typeof t,P=(t,e)=>{if(t.size!==e.size)return!1;for(const i of t)if(!e.has(i))return!1;return!0};function D(t){return"mouseup"===t.type||"click"===t.type||"contextmenu"===t.type}const C=Math.PI,O=2*C,A=O+C,T=Number.POSITIVE_INFINITY,L=C/180,E=C/2,R=C/4,I=2*C/3,z=Math.log10,F=Math.sign;function V(t,e,i){return Math.abs(t-e)<i}function B(t){const e=Math.round(t);t=V(t,e,t/1e3)?e:t;const i=Math.pow(10,Math.floor(z(t))),s=t/i;return(s<=1?1:s<=2?2:s<=5?5:10)*i}function W(t){const e=[],i=Math.sqrt(t);let s;for(s=1;s<i;s++)t%s==0&&(e.push(s),e.push(t/s));return i===(0|i)&&e.push(i),e.sort(((t,e)=>t-e)).pop(),e}function N(t){return!function(t){return"symbol"==typeof t||"object"==typeof t&&null!==t&&!(Symbol.toPrimitive in t||"toString"in t||"valueOf"in t)}(t)&&!isNaN(parseFloat(t))&&isFinite(t)}function H(t,e){const i=Math.round(t);return i-e<=t&&i+e>=t}function j(t,e,i){let s,n,o;for(s=0,n=t.length;s<n;s++)o=t[s][i],isNaN(o)||(e.min=Math.min(e.min,o),e.max=Math.max(e.max,o))}function $(t){return t*(C/180)}function Y(t){return t*(180/C)}function U(t){if(!a(t))return;let e=1,i=0;for(;Math.round(t*e)/e!==t;)e*=10,i++;return i}function X(t,e){const i=e.x-t.x,s=e.y-t.y,n=Math.sqrt(i*i+s*s);let o=Math.atan2(s,i);return o<-.5*C&&(o+=O),{angle:o,distance:n}}function q(t,e){return Math.sqrt(Math.pow(e.x-t.x,2)+Math.pow(e.y-t.y,2))}function K(t,e){return(t-e+A)%O-C}function G(t){return(t%O+O)%O}function J(t,e,i,s){const n=G(t),o=G(e),a=G(i),r=G(o-n),l=G(a-n),h=G(n-o),c=G(n-a);return n===o||n===a||s&&o===a||r>l&&h<c}function Z(t,e,i){return Math.max(e,Math.min(i,t))}function Q(t){return Z(t,-32768,32767)}function tt(t,e,i,s=1e-6){return t>=Math.min(e,i)-s&&t<=Math.max(e,i)+s}function et(t,e,i){i=i||(i=>t[i]<e);let s,n=t.length-1,o=0;for(;n-o>1;)s=o+n>>1,i(s)?o=s:n=s;return{lo:o,hi:n}}const it=(t,e,i,s)=>et(t,i,s?s=>{const n=t[s][e];return n<i||n===i&&t[s+1][e]===i}:s=>t[s][e]<i),st=(t,e,i)=>et(t,i,(s=>t[s][e]>=i));function nt(t,e,i){let s=0,n=t.length;for(;s<n&&t[s]<e;)s++;for(;n>s&&t[n-1]>i;)n--;return s>0||n<t.length?t.slice(s,n):t}const ot=["push","pop","shift","splice","unshift"];function at(t,e){t._chartjs?t._chartjs.listeners.push(e):(Object.defineProperty(t,"_chartjs",{configurable:!0,enumerable:!1,value:{listeners:[e]}}),ot.forEach((e=>{const i="_onData"+w(e),s=t[e];Object.defineProperty(t,e,{configurable:!0,enumerable:!1,value(...e){const n=s.apply(this,e);return t._chartjs.listeners.forEach((t=>{"function"==typeof t[i]&&t[i](...e)})),n}})})))}function rt(t,e){const i=t._chartjs;if(!i)return;const s=i.listeners,n=s.indexOf(e);-1!==n&&s.splice(n,1),s.length>0||(ot.forEach((e=>{delete t[e]})),delete t._chartjs)}function lt(t){const e=new Set(t);return e.size===t.length?t:Array.from(e)}const ht="undefined"==typeof window?function(t){return t()}:window.requestAnimationFrame;function ct(t,e){let i=[],s=!1;return function(...n){i=n,s||(s=!0,ht.call(window,(()=>{s=!1,t.apply(e,i)})))}}function dt(t,e){let i;return function(...s){return e?(clearTimeout(i),i=setTimeout(t,e,s)):t.apply(this,s),e}}const ut=t=>"start"===t?"left":"end"===t?"right":"center",ft=(t,e,i)=>"start"===t?e:"end"===t?i:(e+i)/2,gt=(t,e,i,s)=>t===(s?"left":"right")?i:"center"===t?(e+i)/2:e;function pt(t,e,i){const n=e.length;let o=0,a=n;if(t._sorted){const{iScale:r,vScale:l,_parsed:h}=t,c=t.dataset&&t.dataset.options?t.dataset.options.spanGaps:null,d=r.axis,{min:u,max:f,minDefined:g,maxDefined:p}=r.getUserBounds();if(g){if(o=Math.min(it(h,d,u).lo,i?n:it(e,d,r.getPixelForValue(u)).lo),c){const t=h.slice(0,o+1).reverse().findIndex((t=>!s(t[l.axis])));o-=Math.max(0,t)}o=Z(o,0,n-1)}if(p){let t=Math.max(it(h,r.axis,f,!0).hi+1,i?0:it(e,d,r.getPixelForValue(f),!0).hi+1);if(c){const e=h.slice(t-1).findIndex((t=>!s(t[l.axis])));t+=Math.max(0,e)}a=Z(t,o,n)-o}else a=n-o}return{start:o,count:a}}function mt(t){const{xScale:e,yScale:i,_scaleRanges:s}=t,n={xmin:e.min,xmax:e.max,ymin:i.min,ymax:i.max};if(!s)return t._scaleRanges=n,!0;const o=s.xmin!==e.min||s.xmax!==e.max||s.ymin!==i.min||s.ymax!==i.max;return Object.assign(s,n),o}class xt{constructor(){this._request=null,this._charts=new Map,this._running=!1,this._lastDate=void 0}_notify(t,e,i,s){const n=e.listeners[s],o=e.duration;n.forEach((s=>s({chart:t,initial:e.initial,numSteps:o,currentStep:Math.min(i-e.start,o)})))}_refresh(){this._request||(this._running=!0,this._request=ht.call(window,(()=>{this._update(),this._request=null,this._running&&this._refresh()})))}_update(t=Date.now()){let e=0;this._charts.forEach(((i,s)=>{if(!i.running||!i.items.length)return;const n=i.items;let o,a=n.length-1,r=!1;for(;a>=0;--a)o=n[a],o._active?(o._total>i.duration&&(i.duration=o._total),o.tick(t),r=!0):(n[a]=n[n.length-1],n.pop());r&&(s.draw(),this._notify(s,i,t,"progress")),n.length||(i.running=!1,this._notify(s,i,t,"complete"),i.initial=!1),e+=n.length})),this._lastDate=t,0===e&&(this._running=!1)}_getAnims(t){const e=this._charts;let i=e.get(t);return i||(i={running:!1,initial:!0,items:[],listeners:{complete:[],progress:[]}},e.set(t,i)),i}listen(t,e,i){this._getAnims(t).listeners[e].push(i)}add(t,e){e&&e.length&&this._getAnims(t).items.push(...e)}has(t){return this._getAnims(t).items.length>0}start(t){const e=this._charts.get(t);e&&(e.running=!0,e.start=Date.now(),e.duration=e.items.reduce(((t,e)=>Math.max(t,e._duration)),0),this._refresh())}running(t){if(!this._running)return!1;const e=this._charts.get(t);return!!(e&&e.running&&e.items.length)}stop(t){const e=this._charts.get(t);if(!e||!e.items.length)return;const i=e.items;let s=i.length-1;for(;s>=0;--s)i[s].cancel();e.items=[],this._notify(t,e,Date.now(),"complete")}remove(t){return this._charts.delete(t)}}var bt=new xt;
/*!
 * @kurkle/color v0.3.2
 * https://github.com/kurkle/color#readme
 * (c) 2023 Jukka Kurkela
 * Released under the MIT License
 */function _t(t){return t+.5|0}const yt=(t,e,i)=>Math.max(Math.min(t,i),e);function vt(t){return yt(_t(2.55*t),0,255)}function Mt(t){return yt(_t(255*t),0,255)}function wt(t){return yt(_t(t/2.55)/100,0,1)}function kt(t){return yt(_t(100*t),0,100)}const St={0:0,1:1,2:2,3:3,4:4,5:5,6:6,7:7,8:8,9:9,A:10,B:11,C:12,D:13,E:14,F:15,a:10,b:11,c:12,d:13,e:14,f:15},Pt=[..."0123456789ABCDEF"],Dt=t=>Pt[15&t],Ct=t=>Pt[(240&t)>>4]+Pt[15&t],Ot=t=>(240&t)>>4==(15&t);function At(t){var e=(t=>Ot(t.r)&&Ot(t.g)&&Ot(t.b)&&Ot(t.a))(t)?Dt:Ct;return t?"#"+e(t.r)+e(t.g)+e(t.b)+((t,e)=>t<255?e(t):"")(t.a,e):void 0}const Tt=/^(hsla?|hwb|hsv)\(\s*([-+.e\d]+)(?:deg)?[\s,]+([-+.e\d]+)%[\s,]+([-+.e\d]+)%(?:[\s,]+([-+.e\d]+)(%)?)?\s*\)$/;function Lt(t,e,i){const s=e*Math.min(i,1-i),n=(e,n=(e+t/30)%12)=>i-s*Math.max(Math.min(n-3,9-n,1),-1);return[n(0),n(8),n(4)]}function Et(t,e,i){const s=(s,n=(s+t/60)%6)=>i-i*e*Math.max(Math.min(n,4-n,1),0);return[s(5),s(3),s(1)]}function Rt(t,e,i){const s=Lt(t,1,.5);let n;for(e+i>1&&(n=1/(e+i),e*=n,i*=n),n=0;n<3;n++)s[n]*=1-e-i,s[n]+=e;return s}function It(t){const e=t.r/255,i=t.g/255,s=t.b/255,n=Math.max(e,i,s),o=Math.min(e,i,s),a=(n+o)/2;let r,l,h;return n!==o&&(h=n-o,l=a>.5?h/(2-n-o):h/(n+o),r=function(t,e,i,s,n){return t===n?(e-i)/s+(e<i?6:0):e===n?(i-t)/s+2:(t-e)/s+4}(e,i,s,h,n),r=60*r+.5),[0|r,l||0,a]}function zt(t,e,i,s){return(Array.isArray(e)?t(e[0],e[1],e[2]):t(e,i,s)).map(Mt)}function Ft(t,e,i){return zt(Lt,t,e,i)}function Vt(t){return(t%360+360)%360}function Bt(t){const e=Tt.exec(t);let i,s=255;if(!e)return;e[5]!==i&&(s=e[6]?vt(+e[5]):Mt(+e[5]));const n=Vt(+e[2]),o=+e[3]/100,a=+e[4]/100;return i="hwb"===e[1]?function(t,e,i){return zt(Rt,t,e,i)}(n,o,a):"hsv"===e[1]?function(t,e,i){return zt(Et,t,e,i)}(n,o,a):Ft(n,o,a),{r:i[0],g:i[1],b:i[2],a:s}}const Wt={x:"dark",Z:"light",Y:"re",X:"blu",W:"gr",V:"medium",U:"slate",A:"ee",T:"ol",S:"or",B:"ra",C:"lateg",D:"ights",R:"in",Q:"turquois",E:"hi",P:"ro",O:"al",N:"le",M:"de",L:"yello",F:"en",K:"ch",G:"arks",H:"ea",I:"ightg",J:"wh"},Nt={OiceXe:"f0f8ff",antiquewEte:"faebd7",aqua:"ffff",aquamarRe:"7fffd4",azuY:"f0ffff",beige:"f5f5dc",bisque:"ffe4c4",black:"0",blanKedOmond:"ffebcd",Xe:"ff",XeviTet:"8a2be2",bPwn:"a52a2a",burlywood:"deb887",caMtXe:"5f9ea0",KartYuse:"7fff00",KocTate:"d2691e",cSO:"ff7f50",cSnflowerXe:"6495ed",cSnsilk:"fff8dc",crimson:"dc143c",cyan:"ffff",xXe:"8b",xcyan:"8b8b",xgTMnPd:"b8860b",xWay:"a9a9a9",xgYF:"6400",xgYy:"a9a9a9",xkhaki:"bdb76b",xmagFta:"8b008b",xTivegYF:"556b2f",xSange:"ff8c00",xScEd:"9932cc",xYd:"8b0000",xsOmon:"e9967a",xsHgYF:"8fbc8f",xUXe:"483d8b",xUWay:"2f4f4f",xUgYy:"2f4f4f",xQe:"ced1",xviTet:"9400d3",dAppRk:"ff1493",dApskyXe:"bfff",dimWay:"696969",dimgYy:"696969",dodgerXe:"1e90ff",fiYbrick:"b22222",flSOwEte:"fffaf0",foYstWAn:"228b22",fuKsia:"ff00ff",gaRsbSo:"dcdcdc",ghostwEte:"f8f8ff",gTd:"ffd700",gTMnPd:"daa520",Way:"808080",gYF:"8000",gYFLw:"adff2f",gYy:"808080",honeyMw:"f0fff0",hotpRk:"ff69b4",RdianYd:"cd5c5c",Rdigo:"4b0082",ivSy:"fffff0",khaki:"f0e68c",lavFMr:"e6e6fa",lavFMrXsh:"fff0f5",lawngYF:"7cfc00",NmoncEffon:"fffacd",ZXe:"add8e6",ZcSO:"f08080",Zcyan:"e0ffff",ZgTMnPdLw:"fafad2",ZWay:"d3d3d3",ZgYF:"90ee90",ZgYy:"d3d3d3",ZpRk:"ffb6c1",ZsOmon:"ffa07a",ZsHgYF:"20b2aa",ZskyXe:"87cefa",ZUWay:"778899",ZUgYy:"778899",ZstAlXe:"b0c4de",ZLw:"ffffe0",lime:"ff00",limegYF:"32cd32",lRF:"faf0e6",magFta:"ff00ff",maPon:"800000",VaquamarRe:"66cdaa",VXe:"cd",VScEd:"ba55d3",VpurpN:"9370db",VsHgYF:"3cb371",VUXe:"7b68ee",VsprRggYF:"fa9a",VQe:"48d1cc",VviTetYd:"c71585",midnightXe:"191970",mRtcYam:"f5fffa",mistyPse:"ffe4e1",moccasR:"ffe4b5",navajowEte:"ffdead",navy:"80",Tdlace:"fdf5e6",Tive:"808000",TivedBb:"6b8e23",Sange:"ffa500",SangeYd:"ff4500",ScEd:"da70d6",pOegTMnPd:"eee8aa",pOegYF:"98fb98",pOeQe:"afeeee",pOeviTetYd:"db7093",papayawEp:"ffefd5",pHKpuff:"ffdab9",peru:"cd853f",pRk:"ffc0cb",plum:"dda0dd",powMrXe:"b0e0e6",purpN:"800080",YbeccapurpN:"663399",Yd:"ff0000",Psybrown:"bc8f8f",PyOXe:"4169e1",saddNbPwn:"8b4513",sOmon:"fa8072",sandybPwn:"f4a460",sHgYF:"2e8b57",sHshell:"fff5ee",siFna:"a0522d",silver:"c0c0c0",skyXe:"87ceeb",UXe:"6a5acd",UWay:"708090",UgYy:"708090",snow:"fffafa",sprRggYF:"ff7f",stAlXe:"4682b4",tan:"d2b48c",teO:"8080",tEstN:"d8bfd8",tomato:"ff6347",Qe:"40e0d0",viTet:"ee82ee",JHt:"f5deb3",wEte:"ffffff",wEtesmoke:"f5f5f5",Lw:"ffff00",LwgYF:"9acd32"};let Ht;function jt(t){Ht||(Ht=function(){const t={},e=Object.keys(Nt),i=Object.keys(Wt);let s,n,o,a,r;for(s=0;s<e.length;s++){for(a=r=e[s],n=0;n<i.length;n++)o=i[n],r=r.replace(o,Wt[o]);o=parseInt(Nt[a],16),t[r]=[o>>16&255,o>>8&255,255&o]}return t}(),Ht.transparent=[0,0,0,0]);const e=Ht[t.toLowerCase()];return e&&{r:e[0],g:e[1],b:e[2],a:4===e.length?e[3]:255}}const $t=/^rgba?\(\s*([-+.\d]+)(%)?[\s,]+([-+.e\d]+)(%)?[\s,]+([-+.e\d]+)(%)?(?:[\s,/]+([-+.e\d]+)(%)?)?\s*\)$/;const Yt=t=>t<=.0031308?12.92*t:1.055*Math.pow(t,1/2.4)-.055,Ut=t=>t<=.04045?t/12.92:Math.pow((t+.055)/1.055,2.4);function Xt(t,e,i){if(t){let s=It(t);s[e]=Math.max(0,Math.min(s[e]+s[e]*i,0===e?360:1)),s=Ft(s),t.r=s[0],t.g=s[1],t.b=s[2]}}function qt(t,e){return t?Object.assign(e||{},t):t}function Kt(t){var e={r:0,g:0,b:0,a:255};return Array.isArray(t)?t.length>=3&&(e={r:t[0],g:t[1],b:t[2],a:255},t.length>3&&(e.a=Mt(t[3]))):(e=qt(t,{r:0,g:0,b:0,a:1})).a=Mt(e.a),e}function Gt(t){return"r"===t.charAt(0)?function(t){const e=$t.exec(t);let i,s,n,o=255;if(e){if(e[7]!==i){const t=+e[7];o=e[8]?vt(t):yt(255*t,0,255)}return i=+e[1],s=+e[3],n=+e[5],i=255&(e[2]?vt(i):yt(i,0,255)),s=255&(e[4]?vt(s):yt(s,0,255)),n=255&(e[6]?vt(n):yt(n,0,255)),{r:i,g:s,b:n,a:o}}}(t):Bt(t)}class Jt{constructor(t){if(t instanceof Jt)return t;const e=typeof t;let i;var s,n,o;"object"===e?i=Kt(t):"string"===e&&(o=(s=t).length,"#"===s[0]&&(4===o||5===o?n={r:255&17*St[s[1]],g:255&17*St[s[2]],b:255&17*St[s[3]],a:5===o?17*St[s[4]]:255}:7!==o&&9!==o||(n={r:St[s[1]]<<4|St[s[2]],g:St[s[3]]<<4|St[s[4]],b:St[s[5]]<<4|St[s[6]],a:9===o?St[s[7]]<<4|St[s[8]]:255})),i=n||jt(t)||Gt(t)),this._rgb=i,this._valid=!!i}get valid(){return this._valid}get rgb(){var t=qt(this._rgb);return t&&(t.a=wt(t.a)),t}set rgb(t){this._rgb=Kt(t)}rgbString(){return this._valid?(t=this._rgb)&&(t.a<255?`rgba(${t.r}, ${t.g}, ${t.b}, ${wt(t.a)})`:`rgb(${t.r}, ${t.g}, ${t.b})`):void 0;var t}hexString(){return this._valid?At(this._rgb):void 0}hslString(){return this._valid?function(t){if(!t)return;const e=It(t),i=e[0],s=kt(e[1]),n=kt(e[2]);return t.a<255?`hsla(${i}, ${s}%, ${n}%, ${wt(t.a)})`:`hsl(${i}, ${s}%, ${n}%)`}(this._rgb):void 0}mix(t,e){if(t){const i=this.rgb,s=t.rgb;let n;const o=e===n?.5:e,a=2*o-1,r=i.a-s.a,l=((a*r==-1?a:(a+r)/(1+a*r))+1)/2;n=1-l,i.r=255&l*i.r+n*s.r+.5,i.g=255&l*i.g+n*s.g+.5,i.b=255&l*i.b+n*s.b+.5,i.a=o*i.a+(1-o)*s.a,this.rgb=i}return this}interpolate(t,e){return t&&(this._rgb=function(t,e,i){const s=Ut(wt(t.r)),n=Ut(wt(t.g)),o=Ut(wt(t.b));return{r:Mt(Yt(s+i*(Ut(wt(e.r))-s))),g:Mt(Yt(n+i*(Ut(wt(e.g))-n))),b:Mt(Yt(o+i*(Ut(wt(e.b))-o))),a:t.a+i*(e.a-t.a)}}(this._rgb,t._rgb,e)),this}clone(){return new Jt(this.rgb)}alpha(t){return this._rgb.a=Mt(t),this}clearer(t){return this._rgb.a*=1-t,this}greyscale(){const t=this._rgb,e=_t(.3*t.r+.59*t.g+.11*t.b);return t.r=t.g=t.b=e,this}opaquer(t){return this._rgb.a*=1+t,this}negate(){const t=this._rgb;return t.r=255-t.r,t.g=255-t.g,t.b=255-t.b,this}lighten(t){return Xt(this._rgb,2,t),this}darken(t){return Xt(this._rgb,2,-t),this}saturate(t){return Xt(this._rgb,1,t),this}desaturate(t){return Xt(this._rgb,1,-t),this}rotate(t){return function(t,e){var i=It(t);i[0]=Vt(i[0]+e),i=Ft(i),t.r=i[0],t.g=i[1],t.b=i[2]}(this._rgb,t),this}}function Zt(t){if(t&&"object"==typeof t){const e=t.toString();return"[object CanvasPattern]"===e||"[object CanvasGradient]"===e}return!1}function Qt(t){return Zt(t)?t:new Jt(t)}function te(t){return Zt(t)?t:new Jt(t).saturate(.5).darken(.1).hexString()}const ee=["x","y","borderWidth","radius","tension"],ie=["color","borderColor","backgroundColor"];const se=new Map;function ne(t,e,i){return function(t,e){e=e||{};const i=t+JSON.stringify(e);let s=se.get(i);return s||(s=new Intl.NumberFormat(t,e),se.set(i,s)),s}(e,i).format(t)}const oe={values:t=>n(t)?t:""+t,numeric(t,e,i){if(0===t)return"0";const s=this.chart.options.locale;let n,o=t;if(i.length>1){const e=Math.max(Math.abs(i[0].value),Math.abs(i[i.length-1].value));(e<1e-4||e>1e15)&&(n="scientific"),o=function(t,e){let i=e.length>3?e[2].value-e[1].value:e[1].value-e[0].value;Math.abs(i)>=1&&t!==Math.floor(t)&&(i=t-Math.floor(t));return i}(t,i)}const a=z(Math.abs(o)),r=isNaN(a)?1:Math.max(Math.min(-1*Math.floor(a),20),0),l={notation:n,minimumFractionDigits:r,maximumFractionDigits:r};return Object.assign(l,this.options.ticks.format),ne(t,s,l)},logarithmic(t,e,i){if(0===t)return"0";const s=i[e].significand||t/Math.pow(10,Math.floor(z(t)));return[1,2,3,5,10,15].includes(s)||e>.8*i.length?oe.numeric.call(this,t,e,i):""}};var ae={formatters:oe};const re=Object.create(null),le=Object.create(null);function he(t,e){if(!e)return t;const i=e.split(".");for(let e=0,s=i.length;e<s;++e){const s=i[e];t=t[s]||(t[s]=Object.create(null))}return t}function ce(t,e,i){return"string"==typeof e?x(he(t,e),i):x(he(t,""),e)}class de{constructor(t,e){this.animation=void 0,this.backgroundColor="rgba(0,0,0,0.1)",this.borderColor="rgba(0,0,0,0.1)",this.color="#666",this.datasets={},this.devicePixelRatio=t=>t.chart.platform.getDevicePixelRatio(),this.elements={},this.events=["mousemove","mouseout","click","touchstart","touchmove"],this.font={family:"'Helvetica Neue', 'Helvetica', 'Arial', sans-serif",size:12,style:"normal",lineHeight:1.2,weight:null},this.hover={},this.hoverBackgroundColor=(t,e)=>te(e.backgroundColor),this.hoverBorderColor=(t,e)=>te(e.borderColor),this.hoverColor=(t,e)=>te(e.color),this.indexAxis="x",this.interaction={mode:"nearest",intersect:!0,includeInvisible:!1},this.maintainAspectRatio=!0,this.onHover=null,this.onClick=null,this.parsing=!0,this.plugins={},this.responsive=!0,this.scale=void 0,this.scales={},this.showLine=!0,this.drawActiveElementsOnTop=!0,this.describe(t),this.apply(e)}set(t,e){return ce(this,t,e)}get(t){return he(this,t)}describe(t,e){return ce(le,t,e)}override(t,e){return ce(re,t,e)}route(t,e,i,s){const n=he(this,t),a=he(this,i),r="_"+e;Object.defineProperties(n,{[r]:{value:n[e],writable:!0},[e]:{enumerable:!0,get(){const t=this[r],e=a[s];return o(t)?Object.assign({},e,t):l(t,e)},set(t){this[r]=t}}})}apply(t){t.forEach((t=>t(this)))}}var ue=new de({_scriptable:t=>!t.startsWith("on"),_indexable:t=>"events"!==t,hover:{_fallback:"interaction"},interaction:{_scriptable:!1,_indexable:!1}},[function(t){t.set("animation",{delay:void 0,duration:1e3,easing:"easeOutQuart",fn:void 0,from:void 0,loop:void 0,to:void 0,type:void 0}),t.describe("animation",{_fallback:!1,_indexable:!1,_scriptable:t=>"onProgress"!==t&&"onComplete"!==t&&"fn"!==t}),t.set("animations",{colors:{type:"color",properties:ie},numbers:{type:"number",properties:ee}}),t.describe("animations",{_fallback:"animation"}),t.set("transitions",{active:{animation:{duration:400}},resize:{animation:{duration:0}},show:{animations:{colors:{from:"transparent"},visible:{type:"boolean",duration:0}}},hide:{animations:{colors:{to:"transparent"},visible:{type:"boolean",easing:"linear",fn:t=>0|t}}}})},function(t){t.set("layout",{autoPadding:!0,padding:{top:0,right:0,bottom:0,left:0}})},function(t){t.set("scale",{display:!0,offset:!1,reverse:!1,beginAtZero:!1,bounds:"ticks",clip:!0,grace:0,grid:{display:!0,lineWidth:1,drawOnChartArea:!0,drawTicks:!0,tickLength:8,tickWidth:(t,e)=>e.lineWidth,tickColor:(t,e)=>e.color,offset:!1},border:{display:!0,dash:[],dashOffset:0,width:1},title:{display:!1,text:"",padding:{top:4,bottom:4}},ticks:{minRotation:0,maxRotation:50,mirror:!1,textStrokeWidth:0,textStrokeColor:"",padding:3,display:!0,autoSkip:!0,autoSkipPadding:3,labelOffset:0,callback:ae.formatters.values,minor:{},major:{},align:"center",crossAlign:"near",showLabelBackdrop:!1,backdropColor:"rgba(255, 255, 255, 0.75)",backdropPadding:2}}),t.route("scale.ticks","color","","color"),t.route("scale.grid","color","","borderColor"),t.route("scale.border","color","","borderColor"),t.route("scale.title","color","","color"),t.describe("scale",{_fallback:!1,_scriptable:t=>!t.startsWith("before")&&!t.startsWith("after")&&"callback"!==t&&"parser"!==t,_indexable:t=>"borderDash"!==t&&"tickBorderDash"!==t&&"dash"!==t}),t.describe("scales",{_fallback:"scale"}),t.describe("scale.ticks",{_scriptable:t=>"backdropPadding"!==t&&"callback"!==t,_indexable:t=>"backdropPadding"!==t})}]);function fe(){return"undefined"!=typeof window&&"undefined"!=typeof document}function ge(t){let e=t.parentNode;return e&&"[object ShadowRoot]"===e.toString()&&(e=e.host),e}function pe(t,e,i){let s;return"string"==typeof t?(s=parseInt(t,10),-1!==t.indexOf("%")&&(s=s/100*e.parentNode[i])):s=t,s}const me=t=>t.ownerDocument.defaultView.getComputedStyle(t,null);function xe(t,e){return me(t).getPropertyValue(e)}const be=["top","right","bottom","left"];function _e(t,e,i){const s={};i=i?"-"+i:"";for(let n=0;n<4;n++){const o=be[n];s[o]=parseFloat(t[e+"-"+o+i])||0}return s.width=s.left+s.right,s.height=s.top+s.bottom,s}const ye=(t,e,i)=>(t>0||e>0)&&(!i||!i.shadowRoot);function ve(t,e){if("native"in t)return t;const{canvas:i,currentDevicePixelRatio:s}=e,n=me(i),o="border-box"===n.boxSizing,a=_e(n,"padding"),r=_e(n,"border","width"),{x:l,y:h,box:c}=function(t,e){const i=t.touches,s=i&&i.length?i[0]:t,{offsetX:n,offsetY:o}=s;let a,r,l=!1;if(ye(n,o,t.target))a=n,r=o;else{const t=e.getBoundingClientRect();a=s.clientX-t.left,r=s.clientY-t.top,l=!0}return{x:a,y:r,box:l}}(t,i),d=a.left+(c&&r.left),u=a.top+(c&&r.top);let{width:f,height:g}=e;return o&&(f-=a.width+r.width,g-=a.height+r.height),{x:Math.round((l-d)/f*i.width/s),y:Math.round((h-u)/g*i.height/s)}}const Me=t=>Math.round(10*t)/10;function we(t,e,i,s){const n=me(t),o=_e(n,"margin"),a=pe(n.maxWidth,t,"clientWidth")||T,r=pe(n.maxHeight,t,"clientHeight")||T,l=function(t,e,i){let s,n;if(void 0===e||void 0===i){const o=t&&ge(t);if(o){const t=o.getBoundingClientRect(),a=me(o),r=_e(a,"border","width"),l=_e(a,"padding");e=t.width-l.width-r.width,i=t.height-l.height-r.height,s=pe(a.maxWidth,o,"clientWidth"),n=pe(a.maxHeight,o,"clientHeight")}else e=t.clientWidth,i=t.clientHeight}return{width:e,height:i,maxWidth:s||T,maxHeight:n||T}}(t,e,i);let{width:h,height:c}=l;if("content-box"===n.boxSizing){const t=_e(n,"border","width"),e=_e(n,"padding");h-=e.width+t.width,c-=e.height+t.height}h=Math.max(0,h-o.width),c=Math.max(0,s?h/s:c-o.height),h=Me(Math.min(h,a,l.maxWidth)),c=Me(Math.min(c,r,l.maxHeight)),h&&!c&&(c=Me(h/2));return(void 0!==e||void 0!==i)&&s&&l.height&&c>l.height&&(c=l.height,h=Me(Math.floor(c*s))),{width:h,height:c}}function ke(t,e,i){const s=e||1,n=Me(t.height*s),o=Me(t.width*s);t.height=Me(t.height),t.width=Me(t.width);const a=t.canvas;return a.style&&(i||!a.style.height&&!a.style.width)&&(a.style.height=`${t.height}px`,a.style.width=`${t.width}px`),(t.currentDevicePixelRatio!==s||a.height!==n||a.width!==o)&&(t.currentDevicePixelRatio=s,a.height=n,a.width=o,t.ctx.setTransform(s,0,0,s,0,0),!0)}const Se=function(){let t=!1;try{const e={get passive(){return t=!0,!1}};fe()&&(window.addEventListener("test",null,e),window.removeEventListener("test",null,e))}catch(t){}return t}();function Pe(t,e){const i=xe(t,e),s=i&&i.match(/^(\d+)(\.\d+)?px$/);return s?+s[1]:void 0}function De(t){return!t||s(t.size)||s(t.family)?null:(t.style?t.style+" ":"")+(t.weight?t.weight+" ":"")+t.size+"px "+t.family}function Ce(t,e,i,s,n){let o=e[n];return o||(o=e[n]=t.measureText(n).width,i.push(n)),o>s&&(s=o),s}function Oe(t,e,i,s){let o=(s=s||{}).data=s.data||{},a=s.garbageCollect=s.garbageCollect||[];s.font!==e&&(o=s.data={},a=s.garbageCollect=[],s.font=e),t.save(),t.font=e;let r=0;const l=i.length;let h,c,d,u,f;for(h=0;h<l;h++)if(u=i[h],null==u||n(u)){if(n(u))for(c=0,d=u.length;c<d;c++)f=u[c],null==f||n(f)||(r=Ce(t,o,a,r,f))}else r=Ce(t,o,a,r,u);t.restore();const g=a.length/2;if(g>i.length){for(h=0;h<g;h++)delete o[a[h]];a.splice(0,g)}return r}function Ae(t,e,i){const s=t.currentDevicePixelRatio,n=0!==i?Math.max(i/2,.5):0;return Math.round((e-n)*s)/s+n}function Te(t,e){(e||t)&&((e=e||t.getContext("2d")).save(),e.resetTransform(),e.clearRect(0,0,t.width,t.height),e.restore())}function Le(t,e,i,s){Ee(t,e,i,s,null)}function Ee(t,e,i,s,n){let o,a,r,l,h,c,d,u;const f=e.pointStyle,g=e.rotation,p=e.radius;let m=(g||0)*L;if(f&&"object"==typeof f&&(o=f.toString(),"[object HTMLImageElement]"===o||"[object HTMLCanvasElement]"===o))return t.save(),t.translate(i,s),t.rotate(m),t.drawImage(f,-f.width/2,-f.height/2,f.width,f.height),void t.restore();if(!(isNaN(p)||p<=0)){switch(t.beginPath(),f){default:n?t.ellipse(i,s,n/2,p,0,0,O):t.arc(i,s,p,0,O),t.closePath();break;case"triangle":c=n?n/2:p,t.moveTo(i+Math.sin(m)*c,s-Math.cos(m)*p),m+=I,t.lineTo(i+Math.sin(m)*c,s-Math.cos(m)*p),m+=I,t.lineTo(i+Math.sin(m)*c,s-Math.cos(m)*p),t.closePath();break;case"rectRounded":h=.516*p,l=p-h,a=Math.cos(m+R)*l,d=Math.cos(m+R)*(n?n/2-h:l),r=Math.sin(m+R)*l,u=Math.sin(m+R)*(n?n/2-h:l),t.arc(i-d,s-r,h,m-C,m-E),t.arc(i+u,s-a,h,m-E,m),t.arc(i+d,s+r,h,m,m+E),t.arc(i-u,s+a,h,m+E,m+C),t.closePath();break;case"rect":if(!g){l=Math.SQRT1_2*p,c=n?n/2:l,t.rect(i-c,s-l,2*c,2*l);break}m+=R;case"rectRot":d=Math.cos(m)*(n?n/2:p),a=Math.cos(m)*p,r=Math.sin(m)*p,u=Math.sin(m)*(n?n/2:p),t.moveTo(i-d,s-r),t.lineTo(i+u,s-a),t.lineTo(i+d,s+r),t.lineTo(i-u,s+a),t.closePath();break;case"crossRot":m+=R;case"cross":d=Math.cos(m)*(n?n/2:p),a=Math.cos(m)*p,r=Math.sin(m)*p,u=Math.sin(m)*(n?n/2:p),t.moveTo(i-d,s-r),t.lineTo(i+d,s+r),t.moveTo(i+u,s-a),t.lineTo(i-u,s+a);break;case"star":d=Math.cos(m)*(n?n/2:p),a=Math.cos(m)*p,r=Math.sin(m)*p,u=Math.sin(m)*(n?n/2:p),t.moveTo(i-d,s-r),t.lineTo(i+d,s+r),t.moveTo(i+u,s-a),t.lineTo(i-u,s+a),m+=R,d=Math.cos(m)*(n?n/2:p),a=Math.cos(m)*p,r=Math.sin(m)*p,u=Math.sin(m)*(n?n/2:p),t.moveTo(i-d,s-r),t.lineTo(i+d,s+r),t.moveTo(i+u,s-a),t.lineTo(i-u,s+a);break;case"line":a=n?n/2:Math.cos(m)*p,r=Math.sin(m)*p,t.moveTo(i-a,s-r),t.lineTo(i+a,s+r);break;case"dash":t.moveTo(i,s),t.lineTo(i+Math.cos(m)*(n?n/2:p),s+Math.sin(m)*p);break;case!1:t.closePath()}t.fill(),e.borderWidth>0&&t.stroke()}}function Re(t,e,i){return i=i||.5,!e||t&&t.x>e.left-i&&t.x<e.right+i&&t.y>e.top-i&&t.y<e.bottom+i}function Ie(t,e){t.save(),t.beginPath(),t.rect(e.left,e.top,e.right-e.left,e.bottom-e.top),t.clip()}function ze(t){t.restore()}function Fe(t,e,i,s,n){if(!e)return t.lineTo(i.x,i.y);if("middle"===n){const s=(e.x+i.x)/2;t.lineTo(s,e.y),t.lineTo(s,i.y)}else"after"===n!=!!s?t.lineTo(e.x,i.y):t.lineTo(i.x,e.y);t.lineTo(i.x,i.y)}function Ve(t,e,i,s){if(!e)return t.lineTo(i.x,i.y);t.bezierCurveTo(s?e.cp1x:e.cp2x,s?e.cp1y:e.cp2y,s?i.cp2x:i.cp1x,s?i.cp2y:i.cp1y,i.x,i.y)}function Be(t,e,i,s,n){if(n.strikethrough||n.underline){const o=t.measureText(s),a=e-o.actualBoundingBoxLeft,r=e+o.actualBoundingBoxRight,l=i-o.actualBoundingBoxAscent,h=i+o.actualBoundingBoxDescent,c=n.strikethrough?(l+h)/2:h;t.strokeStyle=t.fillStyle,t.beginPath(),t.lineWidth=n.decorationWidth||2,t.moveTo(a,c),t.lineTo(r,c),t.stroke()}}function We(t,e){const i=t.fillStyle;t.fillStyle=e.color,t.fillRect(e.left,e.top,e.width,e.height),t.fillStyle=i}function Ne(t,e,i,o,a,r={}){const l=n(e)?e:[e],h=r.strokeWidth>0&&""!==r.strokeColor;let c,d;for(t.save(),t.font=a.string,function(t,e){e.translation&&t.translate(e.translation[0],e.translation[1]),s(e.rotation)||t.rotate(e.rotation),e.color&&(t.fillStyle=e.color),e.textAlign&&(t.textAlign=e.textAlign),e.textBaseline&&(t.textBaseline=e.textBaseline)}(t,r),c=0;c<l.length;++c)d=l[c],r.backdrop&&We(t,r.backdrop),h&&(r.strokeColor&&(t.strokeStyle=r.strokeColor),s(r.strokeWidth)||(t.lineWidth=r.strokeWidth),t.strokeText(d,i,o,r.maxWidth)),t.fillText(d,i,o,r.maxWidth),Be(t,i,o,d,r),o+=Number(a.lineHeight);t.restore()}function He(t,e){const{x:i,y:s,w:n,h:o,radius:a}=e;t.arc(i+a.topLeft,s+a.topLeft,a.topLeft,1.5*C,C,!0),t.lineTo(i,s+o-a.bottomLeft),t.arc(i+a.bottomLeft,s+o-a.bottomLeft,a.bottomLeft,C,E,!0),t.lineTo(i+n-a.bottomRight,s+o),t.arc(i+n-a.bottomRight,s+o-a.bottomRight,a.bottomRight,E,0,!0),t.lineTo(i+n,s+a.topRight),t.arc(i+n-a.topRight,s+a.topRight,a.topRight,0,-E,!0),t.lineTo(i+a.topLeft,s)}function je(t,e=[""],i,s,n=(()=>t[0])){const o=i||t;void 0===s&&(s=ti("_fallback",t));const a={[Symbol.toStringTag]:"Object",_cacheable:!0,_scopes:t,_rootScopes:o,_fallback:s,_getTarget:n,override:i=>je([i,...t],e,o,s)};return new Proxy(a,{deleteProperty:(e,i)=>(delete e[i],delete e._keys,delete t[0][i],!0),get:(i,s)=>qe(i,s,(()=>function(t,e,i,s){let n;for(const o of e)if(n=ti(Ue(o,t),i),void 0!==n)return Xe(t,n)?Ze(i,s,t,n):n}(s,e,t,i))),getOwnPropertyDescriptor:(t,e)=>Reflect.getOwnPropertyDescriptor(t._scopes[0],e),getPrototypeOf:()=>Reflect.getPrototypeOf(t[0]),has:(t,e)=>ei(t).includes(e),ownKeys:t=>ei(t),set(t,e,i){const s=t._storage||(t._storage=n());return t[e]=s[e]=i,delete t._keys,!0}})}function $e(t,e,i,s){const a={_cacheable:!1,_proxy:t,_context:e,_subProxy:i,_stack:new Set,_descriptors:Ye(t,s),setContext:e=>$e(t,e,i,s),override:n=>$e(t.override(n),e,i,s)};return new Proxy(a,{deleteProperty:(e,i)=>(delete e[i],delete t[i],!0),get:(t,e,i)=>qe(t,e,(()=>function(t,e,i){const{_proxy:s,_context:a,_subProxy:r,_descriptors:l}=t;let h=s[e];S(h)&&l.isScriptable(e)&&(h=function(t,e,i,s){const{_proxy:n,_context:o,_subProxy:a,_stack:r}=i;if(r.has(t))throw new Error("Recursion detected: "+Array.from(r).join("->")+"->"+t);r.add(t);let l=e(o,a||s);r.delete(t),Xe(t,l)&&(l=Ze(n._scopes,n,t,l));return l}(e,h,t,i));n(h)&&h.length&&(h=function(t,e,i,s){const{_proxy:n,_context:a,_subProxy:r,_descriptors:l}=i;if(void 0!==a.index&&s(t))return e[a.index%e.length];if(o(e[0])){const i=e,s=n._scopes.filter((t=>t!==i));e=[];for(const o of i){const i=Ze(s,n,t,o);e.push($e(i,a,r&&r[t],l))}}return e}(e,h,t,l.isIndexable));Xe(e,h)&&(h=$e(h,a,r&&r[e],l));return h}(t,e,i))),getOwnPropertyDescriptor:(e,i)=>e._descriptors.allKeys?Reflect.has(t,i)?{enumerable:!0,configurable:!0}:void 0:Reflect.getOwnPropertyDescriptor(t,i),getPrototypeOf:()=>Reflect.getPrototypeOf(t),has:(e,i)=>Reflect.has(t,i),ownKeys:()=>Reflect.ownKeys(t),set:(e,i,s)=>(t[i]=s,delete e[i],!0)})}function Ye(t,e={scriptable:!0,indexable:!0}){const{_scriptable:i=e.scriptable,_indexable:s=e.indexable,_allKeys:n=e.allKeys}=t;return{allKeys:n,scriptable:i,indexable:s,isScriptable:S(i)?i:()=>i,isIndexable:S(s)?s:()=>s}}const Ue=(t,e)=>t?t+w(e):e,Xe=(t,e)=>o(e)&&"adapters"!==t&&(null===Object.getPrototypeOf(e)||e.constructor===Object);function qe(t,e,i){if(Object.prototype.hasOwnProperty.call(t,e)||"constructor"===e)return t[e];const s=i();return t[e]=s,s}function Ke(t,e,i){return S(t)?t(e,i):t}const Ge=(t,e)=>!0===t?e:"string"==typeof t?M(e,t):void 0;function Je(t,e,i,s,n){for(const o of e){const e=Ge(i,o);if(e){t.add(e);const o=Ke(e._fallback,i,n);if(void 0!==o&&o!==i&&o!==s)return o}else if(!1===e&&void 0!==s&&i!==s)return null}return!1}function Ze(t,e,i,s){const a=e._rootScopes,r=Ke(e._fallback,i,s),l=[...t,...a],h=new Set;h.add(s);let c=Qe(h,l,i,r||i,s);return null!==c&&((void 0===r||r===i||(c=Qe(h,l,r,c,s),null!==c))&&je(Array.from(h),[""],a,r,(()=>function(t,e,i){const s=t._getTarget();e in s||(s[e]={});const a=s[e];if(n(a)&&o(i))return i;return a||{}}(e,i,s))))}function Qe(t,e,i,s,n){for(;i;)i=Je(t,e,i,s,n);return i}function ti(t,e){for(const i of e){if(!i)continue;const e=i[t];if(void 0!==e)return e}}function ei(t){let e=t._keys;return e||(e=t._keys=function(t){const e=new Set;for(const i of t)for(const t of Object.keys(i).filter((t=>!t.startsWith("_"))))e.add(t);return Array.from(e)}(t._scopes)),e}function ii(t,e,i,s){const{iScale:n}=t,{key:o="r"}=this._parsing,a=new Array(s);let r,l,h,c;for(r=0,l=s;r<l;++r)h=r+i,c=e[h],a[r]={r:n.parse(M(c,o),h)};return a}const si=Number.EPSILON||1e-14,ni=(t,e)=>e<t.length&&!t[e].skip&&t[e],oi=t=>"x"===t?"y":"x";function ai(t,e,i,s){const n=t.skip?e:t,o=e,a=i.skip?e:i,r=q(o,n),l=q(a,o);let h=r/(r+l),c=l/(r+l);h=isNaN(h)?0:h,c=isNaN(c)?0:c;const d=s*h,u=s*c;return{previous:{x:o.x-d*(a.x-n.x),y:o.y-d*(a.y-n.y)},next:{x:o.x+u*(a.x-n.x),y:o.y+u*(a.y-n.y)}}}function ri(t,e="x"){const i=oi(e),s=t.length,n=Array(s).fill(0),o=Array(s);let a,r,l,h=ni(t,0);for(a=0;a<s;++a)if(r=l,l=h,h=ni(t,a+1),l){if(h){const t=h[e]-l[e];n[a]=0!==t?(h[i]-l[i])/t:0}o[a]=r?h?F(n[a-1])!==F(n[a])?0:(n[a-1]+n[a])/2:n[a-1]:n[a]}!function(t,e,i){const s=t.length;let n,o,a,r,l,h=ni(t,0);for(let c=0;c<s-1;++c)l=h,h=ni(t,c+1),l&&h&&(V(e[c],0,si)?i[c]=i[c+1]=0:(n=i[c]/e[c],o=i[c+1]/e[c],r=Math.pow(n,2)+Math.pow(o,2),r<=9||(a=3/Math.sqrt(r),i[c]=n*a*e[c],i[c+1]=o*a*e[c])))}(t,n,o),function(t,e,i="x"){const s=oi(i),n=t.length;let o,a,r,l=ni(t,0);for(let h=0;h<n;++h){if(a=r,r=l,l=ni(t,h+1),!r)continue;const n=r[i],c=r[s];a&&(o=(n-a[i])/3,r[`cp1${i}`]=n-o,r[`cp1${s}`]=c-o*e[h]),l&&(o=(l[i]-n)/3,r[`cp2${i}`]=n+o,r[`cp2${s}`]=c+o*e[h])}}(t,o,e)}function li(t,e,i){return Math.max(Math.min(t,i),e)}function hi(t,e,i,s,n){let o,a,r,l;if(e.spanGaps&&(t=t.filter((t=>!t.skip))),"monotone"===e.cubicInterpolationMode)ri(t,n);else{let i=s?t[t.length-1]:t[0];for(o=0,a=t.length;o<a;++o)r=t[o],l=ai(i,r,t[Math.min(o+1,a-(s?0:1))%a],e.tension),r.cp1x=l.previous.x,r.cp1y=l.previous.y,r.cp2x=l.next.x,r.cp2y=l.next.y,i=r}e.capBezierPoints&&function(t,e){let i,s,n,o,a,r=Re(t[0],e);for(i=0,s=t.length;i<s;++i)a=o,o=r,r=i<s-1&&Re(t[i+1],e),o&&(n=t[i],a&&(n.cp1x=li(n.cp1x,e.left,e.right),n.cp1y=li(n.cp1y,e.top,e.bottom)),r&&(n.cp2x=li(n.cp2x,e.left,e.right),n.cp2y=li(n.cp2y,e.top,e.bottom)))}(t,i)}const ci=t=>0===t||1===t,di=(t,e,i)=>-Math.pow(2,10*(t-=1))*Math.sin((t-e)*O/i),ui=(t,e,i)=>Math.pow(2,-10*t)*Math.sin((t-e)*O/i)+1,fi={linear:t=>t,easeInQuad:t=>t*t,easeOutQuad:t=>-t*(t-2),easeInOutQuad:t=>(t/=.5)<1?.5*t*t:-.5*(--t*(t-2)-1),easeInCubic:t=>t*t*t,easeOutCubic:t=>(t-=1)*t*t+1,easeInOutCubic:t=>(t/=.5)<1?.5*t*t*t:.5*((t-=2)*t*t+2),easeInQuart:t=>t*t*t*t,easeOutQuart:t=>-((t-=1)*t*t*t-1),easeInOutQuart:t=>(t/=.5)<1?.5*t*t*t*t:-.5*((t-=2)*t*t*t-2),easeInQuint:t=>t*t*t*t*t,easeOutQuint:t=>(t-=1)*t*t*t*t+1,easeInOutQuint:t=>(t/=.5)<1?.5*t*t*t*t*t:.5*((t-=2)*t*t*t*t+2),easeInSine:t=>1-Math.cos(t*E),easeOutSine:t=>Math.sin(t*E),easeInOutSine:t=>-.5*(Math.cos(C*t)-1),easeInExpo:t=>0===t?0:Math.pow(2,10*(t-1)),easeOutExpo:t=>1===t?1:1-Math.pow(2,-10*t),easeInOutExpo:t=>ci(t)?t:t<.5?.5*Math.pow(2,10*(2*t-1)):.5*(2-Math.pow(2,-10*(2*t-1))),easeInCirc:t=>t>=1?t:-(Math.sqrt(1-t*t)-1),easeOutCirc:t=>Math.sqrt(1-(t-=1)*t),easeInOutCirc:t=>(t/=.5)<1?-.5*(Math.sqrt(1-t*t)-1):.5*(Math.sqrt(1-(t-=2)*t)+1),easeInElastic:t=>ci(t)?t:di(t,.075,.3),easeOutElastic:t=>ci(t)?t:ui(t,.075,.3),easeInOutElastic(t){const e=.1125;return ci(t)?t:t<.5?.5*di(2*t,e,.45):.5+.5*ui(2*t-1,e,.45)},easeInBack(t){const e=1.70158;return t*t*((e+1)*t-e)},easeOutBack(t){const e=1.70158;return(t-=1)*t*((e+1)*t+e)+1},easeInOutBack(t){let e=1.70158;return(t/=.5)<1?t*t*((1+(e*=1.525))*t-e)*.5:.5*((t-=2)*t*((1+(e*=1.525))*t+e)+2)},easeInBounce:t=>1-fi.easeOutBounce(1-t),easeOutBounce(t){const e=7.5625,i=2.75;return t<1/i?e*t*t:t<2/i?e*(t-=1.5/i)*t+.75:t<2.5/i?e*(t-=2.25/i)*t+.9375:e*(t-=2.625/i)*t+.984375},easeInOutBounce:t=>t<.5?.5*fi.easeInBounce(2*t):.5*fi.easeOutBounce(2*t-1)+.5};function gi(t,e,i,s){return{x:t.x+i*(e.x-t.x),y:t.y+i*(e.y-t.y)}}function pi(t,e,i,s){return{x:t.x+i*(e.x-t.x),y:"middle"===s?i<.5?t.y:e.y:"after"===s?i<1?t.y:e.y:i>0?e.y:t.y}}function mi(t,e,i,s){const n={x:t.cp2x,y:t.cp2y},o={x:e.cp1x,y:e.cp1y},a=gi(t,n,i),r=gi(n,o,i),l=gi(o,e,i),h=gi(a,r,i),c=gi(r,l,i);return gi(h,c,i)}const xi=/^(normal|(\d+(?:\.\d+)?)(px|em|%)?)$/,bi=/^(normal|italic|initial|inherit|unset|(oblique( -?[0-9]?[0-9]deg)?))$/;function _i(t,e){const i=(""+t).match(xi);if(!i||"normal"===i[1])return 1.2*e;switch(t=+i[2],i[3]){case"px":return t;case"%":t/=100}return e*t}const yi=t=>+t||0;function vi(t,e){const i={},s=o(e),n=s?Object.keys(e):e,a=o(t)?s?i=>l(t[i],t[e[i]]):e=>t[e]:()=>t;for(const t of n)i[t]=yi(a(t));return i}function Mi(t){return vi(t,{top:"y",right:"x",bottom:"y",left:"x"})}function wi(t){return vi(t,["topLeft","topRight","bottomLeft","bottomRight"])}function ki(t){const e=Mi(t);return e.width=e.left+e.right,e.height=e.top+e.bottom,e}function Si(t,e){t=t||{},e=e||ue.font;let i=l(t.size,e.size);"string"==typeof i&&(i=parseInt(i,10));let s=l(t.style,e.style);s&&!(""+s).match(bi)&&(console.warn('Invalid font style specified: "'+s+'"'),s=void 0);const n={family:l(t.family,e.family),lineHeight:_i(l(t.lineHeight,e.lineHeight),i),size:i,style:s,weight:l(t.weight,e.weight),string:""};return n.string=De(n),n}function Pi(t,e,i,s){let o,a,r,l=!0;for(o=0,a=t.length;o<a;++o)if(r=t[o],void 0!==r&&(void 0!==e&&"function"==typeof r&&(r=r(e),l=!1),void 0!==i&&n(r)&&(r=r[i%r.length],l=!1),void 0!==r))return s&&!l&&(s.cacheable=!1),r}function Di(t,e,i){const{min:s,max:n}=t,o=c(e,(n-s)/2),a=(t,e)=>i&&0===t?0:t+e;return{min:a(s,-Math.abs(o)),max:a(n,o)}}function Ci(t,e){return Object.assign(Object.create(t),e)}function Oi(t,e,i){return t?function(t,e){return{x:i=>t+t+e-i,setWidth(t){e=t},textAlign:t=>"center"===t?t:"right"===t?"left":"right",xPlus:(t,e)=>t-e,leftForLtr:(t,e)=>t-e}}(e,i):{x:t=>t,setWidth(t){},textAlign:t=>t,xPlus:(t,e)=>t+e,leftForLtr:(t,e)=>t}}function Ai(t,e){let i,s;"ltr"!==e&&"rtl"!==e||(i=t.canvas.style,s=[i.getPropertyValue("direction"),i.getPropertyPriority("direction")],i.setProperty("direction",e,"important"),t.prevTextDirection=s)}function Ti(t,e){void 0!==e&&(delete t.prevTextDirection,t.canvas.style.setProperty("direction",e[0],e[1]))}function Li(t){return"angle"===t?{between:J,compare:K,normalize:G}:{between:tt,compare:(t,e)=>t-e,normalize:t=>t}}function Ei({start:t,end:e,count:i,loop:s,style:n}){return{start:t%i,end:e%i,loop:s&&(e-t+1)%i==0,style:n}}function Ri(t,e,i){if(!i)return[t];const{property:s,start:n,end:o}=i,a=e.length,{compare:r,between:l,normalize:h}=Li(s),{start:c,end:d,loop:u,style:f}=function(t,e,i){const{property:s,start:n,end:o}=i,{between:a,normalize:r}=Li(s),l=e.length;let h,c,{start:d,end:u,loop:f}=t;if(f){for(d+=l,u+=l,h=0,c=l;h<c&&a(r(e[d%l][s]),n,o);++h)d--,u--;d%=l,u%=l}return u<d&&(u+=l),{start:d,end:u,loop:f,style:t.style}}(t,e,i),g=[];let p,m,x,b=!1,_=null;const y=()=>b||l(n,x,p)&&0!==r(n,x),v=()=>!b||0===r(o,p)||l(o,x,p);for(let t=c,i=c;t<=d;++t)m=e[t%a],m.skip||(p=h(m[s]),p!==x&&(b=l(p,n,o),null===_&&y()&&(_=0===r(p,n)?t:i),null!==_&&v()&&(g.push(Ei({start:_,end:t,loop:u,count:a,style:f})),_=null),i=t,x=p));return null!==_&&g.push(Ei({start:_,end:d,loop:u,count:a,style:f})),g}function Ii(t,e){const i=[],s=t.segments;for(let n=0;n<s.length;n++){const o=Ri(s[n],t.points,e);o.length&&i.push(...o)}return i}function zi(t,e){const i=t.points,s=t.options.spanGaps,n=i.length;if(!n)return[];const o=!!t._loop,{start:a,end:r}=function(t,e,i,s){let n=0,o=e-1;if(i&&!s)for(;n<e&&!t[n].skip;)n++;for(;n<e&&t[n].skip;)n++;for(n%=e,i&&(o+=n);o>n&&t[o%e].skip;)o--;return o%=e,{start:n,end:o}}(i,n,o,s);if(!0===s)return Fi(t,[{start:a,end:r,loop:o}],i,e);return Fi(t,function(t,e,i,s){const n=t.length,o=[];let a,r=e,l=t[e];for(a=e+1;a<=i;++a){const i=t[a%n];i.skip||i.stop?l.skip||(s=!1,o.push({start:e%n,end:(a-1)%n,loop:s}),e=r=i.stop?a:null):(r=a,l.skip&&(e=a)),l=i}return null!==r&&o.push({start:e%n,end:r%n,loop:s}),o}(i,a,r<a?r+n:r,!!t._fullLoop&&0===a&&r===n-1),i,e)}function Fi(t,e,i,s){return s&&s.setContext&&i?function(t,e,i,s){const n=t._chart.getContext(),o=Vi(t.options),{_datasetIndex:a,options:{spanGaps:r}}=t,l=i.length,h=[];let c=o,d=e[0].start,u=d;function f(t,e,s,n){const o=r?-1:1;if(t!==e){for(t+=l;i[t%l].skip;)t-=o;for(;i[e%l].skip;)e+=o;t%l!=e%l&&(h.push({start:t%l,end:e%l,loop:s,style:n}),c=n,d=e%l)}}for(const t of e){d=r?d:t.start;let e,o=i[d%l];for(u=d+1;u<=t.end;u++){const r=i[u%l];e=Vi(s.setContext(Ci(n,{type:"segment",p0:o,p1:r,p0DataIndex:(u-1)%l,p1DataIndex:u%l,datasetIndex:a}))),Bi(e,c)&&f(d,u-1,t.loop,c),o=r,c=e}d<u-1&&f(d,u-1,t.loop,c)}return h}(t,e,i,s):e}function Vi(t){return{backgroundColor:t.backgroundColor,borderCapStyle:t.borderCapStyle,borderDash:t.borderDash,borderDashOffset:t.borderDashOffset,borderJoinStyle:t.borderJoinStyle,borderWidth:t.borderWidth,borderColor:t.borderColor}}function Bi(t,e){if(!e)return!1;const i=[],s=function(t,e){return Zt(e)?(i.includes(e)||i.push(e),i.indexOf(e)):e};return JSON.stringify(t,s)!==JSON.stringify(e,s)}function Wi(t,e,i){return t.options.clip?t[i]:e[i]}function Ni(t,e){const i=e._clip;if(i.disabled)return!1;const s=function(t,e){const{xScale:i,yScale:s}=t;return i&&s?{left:Wi(i,e,"left"),right:Wi(i,e,"right"),top:Wi(s,e,"top"),bottom:Wi(s,e,"bottom")}:e}(e,t.chartArea);return{left:!1===i.left?0:s.left-(!0===i.left?0:i.left),right:!1===i.right?t.width:s.right+(!0===i.right?0:i.right),top:!1===i.top?0:s.top-(!0===i.top?0:i.top),bottom:!1===i.bottom?t.height:s.bottom+(!0===i.bottom?0:i.bottom)}}var Hi=Object.freeze({__proto__:null,HALF_PI:E,INFINITY:T,PI:C,PITAU:A,QUARTER_PI:R,RAD_PER_DEG:L,TAU:O,TWO_THIRDS_PI:I,_addGrace:Di,_alignPixel:Ae,_alignStartEnd:ft,_angleBetween:J,_angleDiff:K,_arrayUnique:lt,_attachContext:$e,_bezierCurveTo:Ve,_bezierInterpolation:mi,_boundSegment:Ri,_boundSegments:Ii,_capitalize:w,_computeSegments:zi,_createResolver:je,_decimalPlaces:U,_deprecated:function(t,e,i,s){void 0!==e&&console.warn(t+': "'+i+'" is deprecated. Please use "'+s+'" instead')},_descriptors:Ye,_elementsEqual:f,_factorize:W,_filterBetween:nt,_getParentNode:ge,_getStartAndCountOfVisiblePoints:pt,_int16Range:Q,_isBetween:tt,_isClickEvent:D,_isDomSupported:fe,_isPointInArea:Re,_limitValue:Z,_longestText:Oe,_lookup:et,_lookupByKey:it,_measureText:Ce,_merger:m,_mergerIf:_,_normalizeAngle:G,_parseObjectDataRadialScale:ii,_pointInLine:gi,_readValueToProps:vi,_rlookupByKey:st,_scaleRangesChanged:mt,_setMinAndMaxByKey:j,_splitKey:v,_steppedInterpolation:pi,_steppedLineTo:Fe,_textX:gt,_toLeftRightCenter:ut,_updateBezierControlPoints:hi,addRoundedRectPath:He,almostEquals:V,almostWhole:H,callback:d,clearCanvas:Te,clipArea:Ie,clone:g,color:Qt,createContext:Ci,debounce:dt,defined:k,distanceBetweenPoints:q,drawPoint:Le,drawPointLegend:Ee,each:u,easingEffects:fi,finiteOrDefault:r,fontString:function(t,e,i){return e+" "+t+"px "+i},formatNumber:ne,getAngleFromPoint:X,getDatasetClipArea:Ni,getHoverColor:te,getMaximumSize:we,getRelativePosition:ve,getRtlAdapter:Oi,getStyle:xe,isArray:n,isFinite:a,isFunction:S,isNullOrUndef:s,isNumber:N,isObject:o,isPatternOrGradient:Zt,listenArrayEvents:at,log10:z,merge:x,mergeIf:b,niceNum:B,noop:e,overrideTextDirection:Ai,readUsedSize:Pe,renderText:Ne,requestAnimFrame:ht,resolve:Pi,resolveObjectKey:M,restoreTextDirection:Ti,retinaScale:ke,setsEqual:P,sign:F,splineCurve:ai,splineCurveMonotone:ri,supportsEventListenerOptions:Se,throttled:ct,toDegrees:Y,toDimension:c,toFont:Si,toFontString:De,toLineHeight:_i,toPadding:ki,toPercentage:h,toRadians:$,toTRBL:Mi,toTRBLCorners:wi,uid:i,unclipArea:ze,unlistenArrayEvents:rt,valueOrDefault:l});function ji(t,e,i,n){const{controller:o,data:a,_sorted:r}=t,l=o._cachedMeta.iScale,h=t.dataset&&t.dataset.options?t.dataset.options.spanGaps:null;if(l&&e===l.axis&&"r"!==e&&r&&a.length){const r=l._reversePixels?st:it;if(!n){const n=r(a,e,i);if(h){const{vScale:e}=o._cachedMeta,{_parsed:i}=t,a=i.slice(0,n.lo+1).reverse().findIndex((t=>!s(t[e.axis])));n.lo-=Math.max(0,a);const r=i.slice(n.hi).findIndex((t=>!s(t[e.axis])));n.hi+=Math.max(0,r)}return n}if(o._sharedOptions){const t=a[0],s="function"==typeof t.getRange&&t.getRange(e);if(s){const t=r(a,e,i-s),n=r(a,e,i+s);return{lo:t.lo,hi:n.hi}}}}return{lo:0,hi:a.length-1}}function $i(t,e,i,s,n){const o=t.getSortedVisibleDatasetMetas(),a=i[e];for(let t=0,i=o.length;t<i;++t){const{index:i,data:r}=o[t],{lo:l,hi:h}=ji(o[t],e,a,n);for(let t=l;t<=h;++t){const e=r[t];e.skip||s(e,i,t)}}}function Yi(t,e,i,s,n){const o=[];if(!n&&!t.isPointInArea(e))return o;return $i(t,i,e,(function(i,a,r){(n||Re(i,t.chartArea,0))&&i.inRange(e.x,e.y,s)&&o.push({element:i,datasetIndex:a,index:r})}),!0),o}function Ui(t,e,i,s,n,o){let a=[];const r=function(t){const e=-1!==t.indexOf("x"),i=-1!==t.indexOf("y");return function(t,s){const n=e?Math.abs(t.x-s.x):0,o=i?Math.abs(t.y-s.y):0;return Math.sqrt(Math.pow(n,2)+Math.pow(o,2))}}(i);let l=Number.POSITIVE_INFINITY;return $i(t,i,e,(function(i,h,c){const d=i.inRange(e.x,e.y,n);if(s&&!d)return;const u=i.getCenterPoint(n);if(!(!!o||t.isPointInArea(u))&&!d)return;const f=r(e,u);f<l?(a=[{element:i,datasetIndex:h,index:c}],l=f):f===l&&a.push({element:i,datasetIndex:h,index:c})})),a}function Xi(t,e,i,s,n,o){return o||t.isPointInArea(e)?"r"!==i||s?Ui(t,e,i,s,n,o):function(t,e,i,s){let n=[];return $i(t,i,e,(function(t,i,o){const{startAngle:a,endAngle:r}=t.getProps(["startAngle","endAngle"],s),{angle:l}=X(t,{x:e.x,y:e.y});J(l,a,r)&&n.push({element:t,datasetIndex:i,index:o})})),n}(t,e,i,n):[]}function qi(t,e,i,s,n){const o=[],a="x"===i?"inXRange":"inYRange";let r=!1;return $i(t,i,e,((t,s,l)=>{t[a]&&t[a](e[i],n)&&(o.push({element:t,datasetIndex:s,index:l}),r=r||t.inRange(e.x,e.y,n))})),s&&!r?[]:o}var Ki={evaluateInteractionItems:$i,modes:{index(t,e,i,s){const n=ve(e,t),o=i.axis||"x",a=i.includeInvisible||!1,r=i.intersect?Yi(t,n,o,s,a):Xi(t,n,o,!1,s,a),l=[];return r.length?(t.getSortedVisibleDatasetMetas().forEach((t=>{const e=r[0].index,i=t.data[e];i&&!i.skip&&l.push({element:i,datasetIndex:t.index,index:e})})),l):[]},dataset(t,e,i,s){const n=ve(e,t),o=i.axis||"xy",a=i.includeInvisible||!1;let r=i.intersect?Yi(t,n,o,s,a):Xi(t,n,o,!1,s,a);if(r.length>0){const e=r[0].datasetIndex,i=t.getDatasetMeta(e).data;r=[];for(let t=0;t<i.length;++t)r.push({element:i[t],datasetIndex:e,index:t})}return r},point:(t,e,i,s)=>Yi(t,ve(e,t),i.axis||"xy",s,i.includeInvisible||!1),nearest(t,e,i,s){const n=ve(e,t),o=i.axis||"xy",a=i.includeInvisible||!1;return Xi(t,n,o,i.intersect,s,a)},x:(t,e,i,s)=>qi(t,ve(e,t),"x",i.intersect,s),y:(t,e,i,s)=>qi(t,ve(e,t),"y",i.intersect,s)}};const Gi=["left","top","right","bottom"];function Ji(t,e){return t.filter((t=>t.pos===e))}function Zi(t,e){return t.filter((t=>-1===Gi.indexOf(t.pos)&&t.box.axis===e))}function Qi(t,e){return t.sort(((t,i)=>{const s=e?i:t,n=e?t:i;return s.weight===n.weight?s.index-n.index:s.weight-n.weight}))}function ts(t,e){const i=function(t){const e={};for(const i of t){const{stack:t,pos:s,stackWeight:n}=i;if(!t||!Gi.includes(s))continue;const o=e[t]||(e[t]={count:0,placed:0,weight:0,size:0});o.count++,o.weight+=n}return e}(t),{vBoxMaxWidth:s,hBoxMaxHeight:n}=e;let o,a,r;for(o=0,a=t.length;o<a;++o){r=t[o];const{fullSize:a}=r.box,l=i[r.stack],h=l&&r.stackWeight/l.weight;r.horizontal?(r.width=h?h*s:a&&e.availableWidth,r.height=n):(r.width=s,r.height=h?h*n:a&&e.availableHeight)}return i}function es(t,e,i,s){return Math.max(t[i],e[i])+Math.max(t[s],e[s])}function is(t,e){t.top=Math.max(t.top,e.top),t.left=Math.max(t.left,e.left),t.bottom=Math.max(t.bottom,e.bottom),t.right=Math.max(t.right,e.right)}function ss(t,e,i,s){const{pos:n,box:a}=i,r=t.maxPadding;if(!o(n)){i.size&&(t[n]-=i.size);const e=s[i.stack]||{size:0,count:1};e.size=Math.max(e.size,i.horizontal?a.height:a.width),i.size=e.size/e.count,t[n]+=i.size}a.getPadding&&is(r,a.getPadding());const l=Math.max(0,e.outerWidth-es(r,t,"left","right")),h=Math.max(0,e.outerHeight-es(r,t,"top","bottom")),c=l!==t.w,d=h!==t.h;return t.w=l,t.h=h,i.horizontal?{same:c,other:d}:{same:d,other:c}}function ns(t,e){const i=e.maxPadding;function s(t){const s={left:0,top:0,right:0,bottom:0};return t.forEach((t=>{s[t]=Math.max(e[t],i[t])})),s}return s(t?["left","right"]:["top","bottom"])}function os(t,e,i,s){const n=[];let o,a,r,l,h,c;for(o=0,a=t.length,h=0;o<a;++o){r=t[o],l=r.box,l.update(r.width||e.w,r.height||e.h,ns(r.horizontal,e));const{same:a,other:d}=ss(e,i,r,s);h|=a&&n.length,c=c||d,l.fullSize||n.push(r)}return h&&os(n,e,i,s)||c}function as(t,e,i,s,n){t.top=i,t.left=e,t.right=e+s,t.bottom=i+n,t.width=s,t.height=n}function rs(t,e,i,s){const n=i.padding;let{x:o,y:a}=e;for(const r of t){const t=r.box,l=s[r.stack]||{count:1,placed:0,weight:1},h=r.stackWeight/l.weight||1;if(r.horizontal){const s=e.w*h,o=l.size||t.height;k(l.start)&&(a=l.start),t.fullSize?as(t,n.left,a,i.outerWidth-n.right-n.left,o):as(t,e.left+l.placed,a,s,o),l.start=a,l.placed+=s,a=t.bottom}else{const s=e.h*h,a=l.size||t.width;k(l.start)&&(o=l.start),t.fullSize?as(t,o,n.top,a,i.outerHeight-n.bottom-n.top):as(t,o,e.top+l.placed,a,s),l.start=o,l.placed+=s,o=t.right}}e.x=o,e.y=a}var ls={addBox(t,e){t.boxes||(t.boxes=[]),e.fullSize=e.fullSize||!1,e.position=e.position||"top",e.weight=e.weight||0,e._layers=e._layers||function(){return[{z:0,draw(t){e.draw(t)}}]},t.boxes.push(e)},removeBox(t,e){const i=t.boxes?t.boxes.indexOf(e):-1;-1!==i&&t.boxes.splice(i,1)},configure(t,e,i){e.fullSize=i.fullSize,e.position=i.position,e.weight=i.weight},update(t,e,i,s){if(!t)return;const n=ki(t.options.layout.padding),o=Math.max(e-n.width,0),a=Math.max(i-n.height,0),r=function(t){const e=function(t){const e=[];let i,s,n,o,a,r;for(i=0,s=(t||[]).length;i<s;++i)n=t[i],({position:o,options:{stack:a,stackWeight:r=1}}=n),e.push({index:i,box:n,pos:o,horizontal:n.isHorizontal(),weight:n.weight,stack:a&&o+a,stackWeight:r});return e}(t),i=Qi(e.filter((t=>t.box.fullSize)),!0),s=Qi(Ji(e,"left"),!0),n=Qi(Ji(e,"right")),o=Qi(Ji(e,"top"),!0),a=Qi(Ji(e,"bottom")),r=Zi(e,"x"),l=Zi(e,"y");return{fullSize:i,leftAndTop:s.concat(o),rightAndBottom:n.concat(l).concat(a).concat(r),chartArea:Ji(e,"chartArea"),vertical:s.concat(n).concat(l),horizontal:o.concat(a).concat(r)}}(t.boxes),l=r.vertical,h=r.horizontal;u(t.boxes,(t=>{"function"==typeof t.beforeLayout&&t.beforeLayout()}));const c=l.reduce(((t,e)=>e.box.options&&!1===e.box.options.display?t:t+1),0)||1,d=Object.freeze({outerWidth:e,outerHeight:i,padding:n,availableWidth:o,availableHeight:a,vBoxMaxWidth:o/2/c,hBoxMaxHeight:a/2}),f=Object.assign({},n);is(f,ki(s));const g=Object.assign({maxPadding:f,w:o,h:a,x:n.left,y:n.top},n),p=ts(l.concat(h),d);os(r.fullSize,g,d,p),os(l,g,d,p),os(h,g,d,p)&&os(l,g,d,p),function(t){const e=t.maxPadding;function i(i){const s=Math.max(e[i]-t[i],0);return t[i]+=s,s}t.y+=i("top"),t.x+=i("left"),i("right"),i("bottom")}(g),rs(r.leftAndTop,g,d,p),g.x+=g.w,g.y+=g.h,rs(r.rightAndBottom,g,d,p),t.chartArea={left:g.left,top:g.top,right:g.left+g.w,bottom:g.top+g.h,height:g.h,width:g.w},u(r.chartArea,(e=>{const i=e.box;Object.assign(i,t.chartArea),i.update(g.w,g.h,{left:0,top:0,right:0,bottom:0})}))}};class hs{acquireContext(t,e){}releaseContext(t){return!1}addEventListener(t,e,i){}removeEventListener(t,e,i){}getDevicePixelRatio(){return 1}getMaximumSize(t,e,i,s){return e=Math.max(0,e||t.width),i=i||t.height,{width:e,height:Math.max(0,s?Math.floor(e/s):i)}}isAttached(t){return!0}updateConfig(t){}}class cs extends hs{acquireContext(t){return t&&t.getContext&&t.getContext("2d")||null}updateConfig(t){t.options.animation=!1}}const ds="$chartjs",us={touchstart:"mousedown",touchmove:"mousemove",touchend:"mouseup",pointerenter:"mouseenter",pointerdown:"mousedown",pointermove:"mousemove",pointerup:"mouseup",pointerleave:"mouseout",pointerout:"mouseout"},fs=t=>null===t||""===t;const gs=!!Se&&{passive:!0};function ps(t,e,i){t&&t.canvas&&t.canvas.removeEventListener(e,i,gs)}function ms(t,e){for(const i of t)if(i===e||i.contains(e))return!0}function xs(t,e,i){const s=t.canvas,n=new MutationObserver((t=>{let e=!1;for(const i of t)e=e||ms(i.addedNodes,s),e=e&&!ms(i.removedNodes,s);e&&i()}));return n.observe(document,{childList:!0,subtree:!0}),n}function bs(t,e,i){const s=t.canvas,n=new MutationObserver((t=>{let e=!1;for(const i of t)e=e||ms(i.removedNodes,s),e=e&&!ms(i.addedNodes,s);e&&i()}));return n.observe(document,{childList:!0,subtree:!0}),n}const _s=new Map;let ys=0;function vs(){const t=window.devicePixelRatio;t!==ys&&(ys=t,_s.forEach(((e,i)=>{i.currentDevicePixelRatio!==t&&e()})))}function Ms(t,e,i){const s=t.canvas,n=s&&ge(s);if(!n)return;const o=ct(((t,e)=>{const s=n.clientWidth;i(t,e),s<n.clientWidth&&i()}),window),a=new ResizeObserver((t=>{const e=t[0],i=e.contentRect.width,s=e.contentRect.height;0===i&&0===s||o(i,s)}));return a.observe(n),function(t,e){_s.size||window.addEventListener("resize",vs),_s.set(t,e)}(t,o),a}function ws(t,e,i){i&&i.disconnect(),"resize"===e&&function(t){_s.delete(t),_s.size||window.removeEventListener("resize",vs)}(t)}function ks(t,e,i){const s=t.canvas,n=ct((e=>{null!==t.ctx&&i(function(t,e){const i=us[t.type]||t.type,{x:s,y:n}=ve(t,e);return{type:i,chart:e,native:t,x:void 0!==s?s:null,y:void 0!==n?n:null}}(e,t))}),t);return function(t,e,i){t&&t.addEventListener(e,i,gs)}(s,e,n),n}class Ss extends hs{acquireContext(t,e){const i=t&&t.getContext&&t.getContext("2d");return i&&i.canvas===t?(function(t,e){const i=t.style,s=t.getAttribute("height"),n=t.getAttribute("width");if(t[ds]={initial:{height:s,width:n,style:{display:i.display,height:i.height,width:i.width}}},i.display=i.display||"block",i.boxSizing=i.boxSizing||"border-box",fs(n)){const e=Pe(t,"width");void 0!==e&&(t.width=e)}if(fs(s))if(""===t.style.height)t.height=t.width/(e||2);else{const e=Pe(t,"height");void 0!==e&&(t.height=e)}}(t,e),i):null}releaseContext(t){const e=t.canvas;if(!e[ds])return!1;const i=e[ds].initial;["height","width"].forEach((t=>{const n=i[t];s(n)?e.removeAttribute(t):e.setAttribute(t,n)}));const n=i.style||{};return Object.keys(n).forEach((t=>{e.style[t]=n[t]})),e.width=e.width,delete e[ds],!0}addEventListener(t,e,i){this.removeEventListener(t,e);const s=t.$proxies||(t.$proxies={}),n={attach:xs,detach:bs,resize:Ms}[e]||ks;s[e]=n(t,e,i)}removeEventListener(t,e){const i=t.$proxies||(t.$proxies={}),s=i[e];if(!s)return;({attach:ws,detach:ws,resize:ws}[e]||ps)(t,e,s),i[e]=void 0}getDevicePixelRatio(){return window.devicePixelRatio}getMaximumSize(t,e,i,s){return we(t,e,i,s)}isAttached(t){const e=t&&ge(t);return!(!e||!e.isConnected)}}function Ps(t){return!fe()||"undefined"!=typeof OffscreenCanvas&&t instanceof OffscreenCanvas?cs:Ss}var Ds=Object.freeze({__proto__:null,BasePlatform:hs,BasicPlatform:cs,DomPlatform:Ss,_detectPlatform:Ps});const Cs="transparent",Os={boolean:(t,e,i)=>i>.5?e:t,color(t,e,i){const s=Qt(t||Cs),n=s.valid&&Qt(e||Cs);return n&&n.valid?n.mix(s,i).hexString():e},number:(t,e,i)=>t+(e-t)*i};class As{constructor(t,e,i,s){const n=e[i];s=Pi([t.to,s,n,t.from]);const o=Pi([t.from,n,s]);this._active=!0,this._fn=t.fn||Os[t.type||typeof o],this._easing=fi[t.easing]||fi.linear,this._start=Math.floor(Date.now()+(t.delay||0)),this._duration=this._total=Math.floor(t.duration),this._loop=!!t.loop,this._target=e,this._prop=i,this._from=o,this._to=s,this._promises=void 0}active(){return this._active}update(t,e,i){if(this._active){this._notify(!1);const s=this._target[this._prop],n=i-this._start,o=this._duration-n;this._start=i,this._duration=Math.floor(Math.max(o,t.duration)),this._total+=n,this._loop=!!t.loop,this._to=Pi([t.to,e,s,t.from]),this._from=Pi([t.from,s,e])}}cancel(){this._active&&(this.tick(Date.now()),this._active=!1,this._notify(!1))}tick(t){const e=t-this._start,i=this._duration,s=this._prop,n=this._from,o=this._loop,a=this._to;let r;if(this._active=n!==a&&(o||e<i),!this._active)return this._target[s]=a,void this._notify(!0);e<0?this._target[s]=n:(r=e/i%2,r=o&&r>1?2-r:r,r=this._easing(Math.min(1,Math.max(0,r))),this._target[s]=this._fn(n,a,r))}wait(){const t=this._promises||(this._promises=[]);return new Promise(((e,i)=>{t.push({res:e,rej:i})}))}_notify(t){const e=t?"res":"rej",i=this._promises||[];for(let t=0;t<i.length;t++)i[t][e]()}}class Ts{constructor(t,e){this._chart=t,this._properties=new Map,this.configure(e)}configure(t){if(!o(t))return;const e=Object.keys(ue.animation),i=this._properties;Object.getOwnPropertyNames(t).forEach((s=>{const a=t[s];if(!o(a))return;const r={};for(const t of e)r[t]=a[t];(n(a.properties)&&a.properties||[s]).forEach((t=>{t!==s&&i.has(t)||i.set(t,r)}))}))}_animateOptions(t,e){const i=e.options,s=function(t,e){if(!e)return;let i=t.options;if(!i)return void(t.options=e);i.$shared&&(t.options=i=Object.assign({},i,{$shared:!1,$animations:{}}));return i}(t,i);if(!s)return[];const n=this._createAnimations(s,i);return i.$shared&&function(t,e){const i=[],s=Object.keys(e);for(let e=0;e<s.length;e++){const n=t[s[e]];n&&n.active()&&i.push(n.wait())}return Promise.all(i)}(t.options.$animations,i).then((()=>{t.options=i}),(()=>{})),n}_createAnimations(t,e){const i=this._properties,s=[],n=t.$animations||(t.$animations={}),o=Object.keys(e),a=Date.now();let r;for(r=o.length-1;r>=0;--r){const l=o[r];if("$"===l.charAt(0))continue;if("options"===l){s.push(...this._animateOptions(t,e));continue}const h=e[l];let c=n[l];const d=i.get(l);if(c){if(d&&c.active()){c.update(d,h,a);continue}c.cancel()}d&&d.duration?(n[l]=c=new As(d,t,l,h),s.push(c)):t[l]=h}return s}update(t,e){if(0===this._properties.size)return void Object.assign(t,e);const i=this._createAnimations(t,e);return i.length?(bt.add(this._chart,i),!0):void 0}}function Ls(t,e){const i=t&&t.options||{},s=i.reverse,n=void 0===i.min?e:0,o=void 0===i.max?e:0;return{start:s?o:n,end:s?n:o}}function Es(t,e){const i=[],s=t._getSortedDatasetMetas(e);let n,o;for(n=0,o=s.length;n<o;++n)i.push(s[n].index);return i}function Rs(t,e,i,s={}){const n=t.keys,o="single"===s.mode;let r,l,h,c;if(null===e)return;let d=!1;for(r=0,l=n.length;r<l;++r){if(h=+n[r],h===i){if(d=!0,s.all)continue;break}c=t.values[h],a(c)&&(o||0===e||F(e)===F(c))&&(e+=c)}return d||s.all?e:0}function Is(t,e){const i=t&&t.options.stacked;return i||void 0===i&&void 0!==e.stack}function zs(t,e,i){const s=t[e]||(t[e]={});return s[i]||(s[i]={})}function Fs(t,e,i,s){for(const n of e.getMatchingVisibleMetas(s).reverse()){const e=t[n.index];if(i&&e>0||!i&&e<0)return n.index}return null}function Vs(t,e){const{chart:i,_cachedMeta:s}=t,n=i._stacks||(i._stacks={}),{iScale:o,vScale:a,index:r}=s,l=o.axis,h=a.axis,c=function(t,e,i){return`${t.id}.${e.id}.${i.stack||i.type}`}(o,a,s),d=e.length;let u;for(let t=0;t<d;++t){const i=e[t],{[l]:o,[h]:d}=i;u=(i._stacks||(i._stacks={}))[h]=zs(n,c,o),u[r]=d,u._top=Fs(u,a,!0,s.type),u._bottom=Fs(u,a,!1,s.type);(u._visualValues||(u._visualValues={}))[r]=d}}function Bs(t,e){const i=t.scales;return Object.keys(i).filter((t=>i[t].axis===e)).shift()}function Ws(t,e){const i=t.controller.index,s=t.vScale&&t.vScale.axis;if(s){e=e||t._parsed;for(const t of e){const e=t._stacks;if(!e||void 0===e[s]||void 0===e[s][i])return;delete e[s][i],void 0!==e[s]._visualValues&&void 0!==e[s]._visualValues[i]&&delete e[s]._visualValues[i]}}}const Ns=t=>"reset"===t||"none"===t,Hs=(t,e)=>e?t:Object.assign({},t);class js{static defaults={};static datasetElementType=null;static dataElementType=null;constructor(t,e){this.chart=t,this._ctx=t.ctx,this.index=e,this._cachedDataOpts={},this._cachedMeta=this.getMeta(),this._type=this._cachedMeta.type,this.options=void 0,this._parsing=!1,this._data=void 0,this._objectData=void 0,this._sharedOptions=void 0,this._drawStart=void 0,this._drawCount=void 0,this.enableOptionSharing=!1,this.supportsDecimation=!1,this.$context=void 0,this._syncList=[],this.datasetElementType=new.target.datasetElementType,this.dataElementType=new.target.dataElementType,this.initialize()}initialize(){const t=this._cachedMeta;this.configure(),this.linkScales(),t._stacked=Is(t.vScale,t),this.addElements(),this.options.fill&&!this.chart.isPluginEnabled("filler")&&console.warn("Tried to use the 'fill' option without the 'Filler' plugin enabled. Please import and register the 'Filler' plugin and make sure it is not disabled in the options")}updateIndex(t){this.index!==t&&Ws(this._cachedMeta),this.index=t}linkScales(){const t=this.chart,e=this._cachedMeta,i=this.getDataset(),s=(t,e,i,s)=>"x"===t?e:"r"===t?s:i,n=e.xAxisID=l(i.xAxisID,Bs(t,"x")),o=e.yAxisID=l(i.yAxisID,Bs(t,"y")),a=e.rAxisID=l(i.rAxisID,Bs(t,"r")),r=e.indexAxis,h=e.iAxisID=s(r,n,o,a),c=e.vAxisID=s(r,o,n,a);e.xScale=this.getScaleForId(n),e.yScale=this.getScaleForId(o),e.rScale=this.getScaleForId(a),e.iScale=this.getScaleForId(h),e.vScale=this.getScaleForId(c)}getDataset(){return this.chart.data.datasets[this.index]}getMeta(){return this.chart.getDatasetMeta(this.index)}getScaleForId(t){return this.chart.scales[t]}_getOtherScale(t){const e=this._cachedMeta;return t===e.iScale?e.vScale:e.iScale}reset(){this._update("reset")}_destroy(){const t=this._cachedMeta;this._data&&rt(this._data,this),t._stacked&&Ws(t)}_dataCheck(){const t=this.getDataset(),e=t.data||(t.data=[]),i=this._data;if(o(e)){const t=this._cachedMeta;this._data=function(t,e){const{iScale:i,vScale:s}=e,n="x"===i.axis?"x":"y",o="x"===s.axis?"x":"y",a=Object.keys(t),r=new Array(a.length);let l,h,c;for(l=0,h=a.length;l<h;++l)c=a[l],r[l]={[n]:c,[o]:t[c]};return r}(e,t)}else if(i!==e){if(i){rt(i,this);const t=this._cachedMeta;Ws(t),t._parsed=[]}e&&Object.isExtensible(e)&&at(e,this),this._syncList=[],this._data=e}}addElements(){const t=this._cachedMeta;this._dataCheck(),this.datasetElementType&&(t.dataset=new this.datasetElementType)}buildOrUpdateElements(t){const e=this._cachedMeta,i=this.getDataset();let s=!1;this._dataCheck();const n=e._stacked;e._stacked=Is(e.vScale,e),e.stack!==i.stack&&(s=!0,Ws(e),e.stack=i.stack),this._resyncElements(t),(s||n!==e._stacked)&&(Vs(this,e._parsed),e._stacked=Is(e.vScale,e))}configure(){const t=this.chart.config,e=t.datasetScopeKeys(this._type),i=t.getOptionScopes(this.getDataset(),e,!0);this.options=t.createResolver(i,this.getContext()),this._parsing=this.options.parsing,this._cachedDataOpts={}}parse(t,e){const{_cachedMeta:i,_data:s}=this,{iScale:a,_stacked:r}=i,l=a.axis;let h,c,d,u=0===t&&e===s.length||i._sorted,f=t>0&&i._parsed[t-1];if(!1===this._parsing)i._parsed=s,i._sorted=!0,d=s;else{d=n(s[t])?this.parseArrayData(i,s,t,e):o(s[t])?this.parseObjectData(i,s,t,e):this.parsePrimitiveData(i,s,t,e);const a=()=>null===c[l]||f&&c[l]<f[l];for(h=0;h<e;++h)i._parsed[h+t]=c=d[h],u&&(a()&&(u=!1),f=c);i._sorted=u}r&&Vs(this,d)}parsePrimitiveData(t,e,i,s){const{iScale:n,vScale:o}=t,a=n.axis,r=o.axis,l=n.getLabels(),h=n===o,c=new Array(s);let d,u,f;for(d=0,u=s;d<u;++d)f=d+i,c[d]={[a]:h||n.parse(l[f],f),[r]:o.parse(e[f],f)};return c}parseArrayData(t,e,i,s){const{xScale:n,yScale:o}=t,a=new Array(s);let r,l,h,c;for(r=0,l=s;r<l;++r)h=r+i,c=e[h],a[r]={x:n.parse(c[0],h),y:o.parse(c[1],h)};return a}parseObjectData(t,e,i,s){const{xScale:n,yScale:o}=t,{xAxisKey:a="x",yAxisKey:r="y"}=this._parsing,l=new Array(s);let h,c,d,u;for(h=0,c=s;h<c;++h)d=h+i,u=e[d],l[h]={x:n.parse(M(u,a),d),y:o.parse(M(u,r),d)};return l}getParsed(t){return this._cachedMeta._parsed[t]}getDataElement(t){return this._cachedMeta.data[t]}applyStack(t,e,i){const s=this.chart,n=this._cachedMeta,o=e[t.axis];return Rs({keys:Es(s,!0),values:e._stacks[t.axis]._visualValues},o,n.index,{mode:i})}updateRangeFromParsed(t,e,i,s){const n=i[e.axis];let o=null===n?NaN:n;const a=s&&i._stacks[e.axis];s&&a&&(s.values=a,o=Rs(s,n,this._cachedMeta.index)),t.min=Math.min(t.min,o),t.max=Math.max(t.max,o)}getMinMax(t,e){const i=this._cachedMeta,s=i._parsed,n=i._sorted&&t===i.iScale,o=s.length,r=this._getOtherScale(t),l=((t,e,i)=>t&&!e.hidden&&e._stacked&&{keys:Es(i,!0),values:null})(e,i,this.chart),h={min:Number.POSITIVE_INFINITY,max:Number.NEGATIVE_INFINITY},{min:c,max:d}=function(t){const{min:e,max:i,minDefined:s,maxDefined:n}=t.getUserBounds();return{min:s?e:Number.NEGATIVE_INFINITY,max:n?i:Number.POSITIVE_INFINITY}}(r);let u,f;function g(){f=s[u];const e=f[r.axis];return!a(f[t.axis])||c>e||d<e}for(u=0;u<o&&(g()||(this.updateRangeFromParsed(h,t,f,l),!n));++u);if(n)for(u=o-1;u>=0;--u)if(!g()){this.updateRangeFromParsed(h,t,f,l);break}return h}getAllParsedValues(t){const e=this._cachedMeta._parsed,i=[];let s,n,o;for(s=0,n=e.length;s<n;++s)o=e[s][t.axis],a(o)&&i.push(o);return i}getMaxOverflow(){return!1}getLabelAndValue(t){const e=this._cachedMeta,i=e.iScale,s=e.vScale,n=this.getParsed(t);return{label:i?""+i.getLabelForValue(n[i.axis]):"",value:s?""+s.getLabelForValue(n[s.axis]):""}}_update(t){const e=this._cachedMeta;this.update(t||"default"),e._clip=function(t){let e,i,s,n;return o(t)?(e=t.top,i=t.right,s=t.bottom,n=t.left):e=i=s=n=t,{top:e,right:i,bottom:s,left:n,disabled:!1===t}}(l(this.options.clip,function(t,e,i){if(!1===i)return!1;const s=Ls(t,i),n=Ls(e,i);return{top:n.end,right:s.end,bottom:n.start,left:s.start}}(e.xScale,e.yScale,this.getMaxOverflow())))}update(t){}draw(){const t=this._ctx,e=this.chart,i=this._cachedMeta,s=i.data||[],n=e.chartArea,o=[],a=this._drawStart||0,r=this._drawCount||s.length-a,l=this.options.drawActiveElementsOnTop;let h;for(i.dataset&&i.dataset.draw(t,n,a,r),h=a;h<a+r;++h){const e=s[h];e.hidden||(e.active&&l?o.push(e):e.draw(t,n))}for(h=0;h<o.length;++h)o[h].draw(t,n)}getStyle(t,e){const i=e?"active":"default";return void 0===t&&this._cachedMeta.dataset?this.resolveDatasetElementOptions(i):this.resolveDataElementOptions(t||0,i)}getContext(t,e,i){const s=this.getDataset();let n;if(t>=0&&t<this._cachedMeta.data.length){const e=this._cachedMeta.data[t];n=e.$context||(e.$context=function(t,e,i){return Ci(t,{active:!1,dataIndex:e,parsed:void 0,raw:void 0,element:i,index:e,mode:"default",type:"data"})}(this.getContext(),t,e)),n.parsed=this.getParsed(t),n.raw=s.data[t],n.index=n.dataIndex=t}else n=this.$context||(this.$context=function(t,e){return Ci(t,{active:!1,dataset:void 0,datasetIndex:e,index:e,mode:"default",type:"dataset"})}(this.chart.getContext(),this.index)),n.dataset=s,n.index=n.datasetIndex=this.index;return n.active=!!e,n.mode=i,n}resolveDatasetElementOptions(t){return this._resolveElementOptions(this.datasetElementType.id,t)}resolveDataElementOptions(t,e){return this._resolveElementOptions(this.dataElementType.id,e,t)}_resolveElementOptions(t,e="default",i){const s="active"===e,n=this._cachedDataOpts,o=t+"-"+e,a=n[o],r=this.enableOptionSharing&&k(i);if(a)return Hs(a,r);const l=this.chart.config,h=l.datasetElementScopeKeys(this._type,t),c=s?[`${t}Hover`,"hover",t,""]:[t,""],d=l.getOptionScopes(this.getDataset(),h),u=Object.keys(ue.elements[t]),f=l.resolveNamedOptions(d,u,(()=>this.getContext(i,s,e)),c);return f.$shared&&(f.$shared=r,n[o]=Object.freeze(Hs(f,r))),f}_resolveAnimations(t,e,i){const s=this.chart,n=this._cachedDataOpts,o=`animation-${e}`,a=n[o];if(a)return a;let r;if(!1!==s.options.animation){const s=this.chart.config,n=s.datasetAnimationScopeKeys(this._type,e),o=s.getOptionScopes(this.getDataset(),n);r=s.createResolver(o,this.getContext(t,i,e))}const l=new Ts(s,r&&r.animations);return r&&r._cacheable&&(n[o]=Object.freeze(l)),l}getSharedOptions(t){if(t.$shared)return this._sharedOptions||(this._sharedOptions=Object.assign({},t))}includeOptions(t,e){return!e||Ns(t)||this.chart._animationsDisabled}_getSharedOptions(t,e){const i=this.resolveDataElementOptions(t,e),s=this._sharedOptions,n=this.getSharedOptions(i),o=this.includeOptions(e,n)||n!==s;return this.updateSharedOptions(n,e,i),{sharedOptions:n,includeOptions:o}}updateElement(t,e,i,s){Ns(s)?Object.assign(t,i):this._resolveAnimations(e,s).update(t,i)}updateSharedOptions(t,e,i){t&&!Ns(e)&&this._resolveAnimations(void 0,e).update(t,i)}_setStyle(t,e,i,s){t.active=s;const n=this.getStyle(e,s);this._resolveAnimations(e,i,s).update(t,{options:!s&&this.getSharedOptions(n)||n})}removeHoverStyle(t,e,i){this._setStyle(t,i,"active",!1)}setHoverStyle(t,e,i){this._setStyle(t,i,"active",!0)}_removeDatasetHoverStyle(){const t=this._cachedMeta.dataset;t&&this._setStyle(t,void 0,"active",!1)}_setDatasetHoverStyle(){const t=this._cachedMeta.dataset;t&&this._setStyle(t,void 0,"active",!0)}_resyncElements(t){const e=this._data,i=this._cachedMeta.data;for(const[t,e,i]of this._syncList)this[t](e,i);this._syncList=[];const s=i.length,n=e.length,o=Math.min(n,s);o&&this.parse(0,o),n>s?this._insertElements(s,n-s,t):n<s&&this._removeElements(n,s-n)}_insertElements(t,e,i=!0){const s=this._cachedMeta,n=s.data,o=t+e;let a;const r=t=>{for(t.length+=e,a=t.length-1;a>=o;a--)t[a]=t[a-e]};for(r(n),a=t;a<o;++a)n[a]=new this.dataElementType;this._parsing&&r(s._parsed),this.parse(t,e),i&&this.updateElements(n,t,e,"reset")}updateElements(t,e,i,s){}_removeElements(t,e){const i=this._cachedMeta;if(this._parsing){const s=i._parsed.splice(t,e);i._stacked&&Ws(i,s)}i.data.splice(t,e)}_sync(t){if(this._parsing)this._syncList.push(t);else{const[e,i,s]=t;this[e](i,s)}this.chart._dataChanges.push([this.index,...t])}_onDataPush(){const t=arguments.length;this._sync(["_insertElements",this.getDataset().data.length-t,t])}_onDataPop(){this._sync(["_removeElements",this._cachedMeta.data.length-1,1])}_onDataShift(){this._sync(["_removeElements",0,1])}_onDataSplice(t,e){e&&this._sync(["_removeElements",t,e]);const i=arguments.length-2;i&&this._sync(["_insertElements",t,i])}_onDataUnshift(){this._sync(["_insertElements",0,arguments.length])}}class $s{static defaults={};static defaultRoutes=void 0;x;y;active=!1;options;$animations;tooltipPosition(t){const{x:e,y:i}=this.getProps(["x","y"],t);return{x:e,y:i}}hasValue(){return N(this.x)&&N(this.y)}getProps(t,e){const i=this.$animations;if(!e||!i)return this;const s={};return t.forEach((t=>{s[t]=i[t]&&i[t].active()?i[t]._to:this[t]})),s}}function Ys(t,e){const i=t.options.ticks,n=function(t){const e=t.options.offset,i=t._tickSize(),s=t._length/i+(e?0:1),n=t._maxLength/i;return Math.floor(Math.min(s,n))}(t),o=Math.min(i.maxTicksLimit||n,n),a=i.major.enabled?function(t){const e=[];let i,s;for(i=0,s=t.length;i<s;i++)t[i].major&&e.push(i);return e}(e):[],r=a.length,l=a[0],h=a[r-1],c=[];if(r>o)return function(t,e,i,s){let n,o=0,a=i[0];for(s=Math.ceil(s),n=0;n<t.length;n++)n===a&&(e.push(t[n]),o++,a=i[o*s])}(e,c,a,r/o),c;const d=function(t,e,i){const s=function(t){const e=t.length;let i,s;if(e<2)return!1;for(s=t[0],i=1;i<e;++i)if(t[i]-t[i-1]!==s)return!1;return s}(t),n=e.length/i;if(!s)return Math.max(n,1);const o=W(s);for(let t=0,e=o.length-1;t<e;t++){const e=o[t];if(e>n)return e}return Math.max(n,1)}(a,e,o);if(r>0){let t,i;const n=r>1?Math.round((h-l)/(r-1)):null;for(Us(e,c,d,s(n)?0:l-n,l),t=0,i=r-1;t<i;t++)Us(e,c,d,a[t],a[t+1]);return Us(e,c,d,h,s(n)?e.length:h+n),c}return Us(e,c,d),c}function Us(t,e,i,s,n){const o=l(s,0),a=Math.min(l(n,t.length),t.length);let r,h,c,d=0;for(i=Math.ceil(i),n&&(r=n-s,i=r/Math.floor(r/i)),c=o;c<0;)d++,c=Math.round(o+d*i);for(h=Math.max(o,0);h<a;h++)h===c&&(e.push(t[h]),d++,c=Math.round(o+d*i))}const Xs=(t,e,i)=>"top"===e||"left"===e?t[e]+i:t[e]-i,qs=(t,e)=>Math.min(e||t,t);function Ks(t,e){const i=[],s=t.length/e,n=t.length;let o=0;for(;o<n;o+=s)i.push(t[Math.floor(o)]);return i}function Gs(t,e,i){const s=t.ticks.length,n=Math.min(e,s-1),o=t._startPixel,a=t._endPixel,r=1e-6;let l,h=t.getPixelForTick(n);if(!(i&&(l=1===s?Math.max(h-o,a-h):0===e?(t.getPixelForTick(1)-h)/2:(h-t.getPixelForTick(n-1))/2,h+=n<e?l:-l,h<o-r||h>a+r)))return h}function Js(t){return t.drawTicks?t.tickLength:0}function Zs(t,e){if(!t.display)return 0;const i=Si(t.font,e),s=ki(t.padding);return(n(t.text)?t.text.length:1)*i.lineHeight+s.height}function Qs(t,e,i){let s=ut(t);return(i&&"right"!==e||!i&&"right"===e)&&(s=(t=>"left"===t?"right":"right"===t?"left":t)(s)),s}class tn extends $s{constructor(t){super(),this.id=t.id,this.type=t.type,this.options=void 0,this.ctx=t.ctx,this.chart=t.chart,this.top=void 0,this.bottom=void 0,this.left=void 0,this.right=void 0,this.width=void 0,this.height=void 0,this._margins={left:0,right:0,top:0,bottom:0},this.maxWidth=void 0,this.maxHeight=void 0,this.paddingTop=void 0,this.paddingBottom=void 0,this.paddingLeft=void 0,this.paddingRight=void 0,this.axis=void 0,this.labelRotation=void 0,this.min=void 0,this.max=void 0,this._range=void 0,this.ticks=[],this._gridLineItems=null,this._labelItems=null,this._labelSizes=null,this._length=0,this._maxLength=0,this._longestTextCache={},this._startPixel=void 0,this._endPixel=void 0,this._reversePixels=!1,this._userMax=void 0,this._userMin=void 0,this._suggestedMax=void 0,this._suggestedMin=void 0,this._ticksLength=0,this._borderValue=0,this._cache={},this._dataLimitsCached=!1,this.$context=void 0}init(t){this.options=t.setContext(this.getContext()),this.axis=t.axis,this._userMin=this.parse(t.min),this._userMax=this.parse(t.max),this._suggestedMin=this.parse(t.suggestedMin),this._suggestedMax=this.parse(t.suggestedMax)}parse(t,e){return t}getUserBounds(){let{_userMin:t,_userMax:e,_suggestedMin:i,_suggestedMax:s}=this;return t=r(t,Number.POSITIVE_INFINITY),e=r(e,Number.NEGATIVE_INFINITY),i=r(i,Number.POSITIVE_INFINITY),s=r(s,Number.NEGATIVE_INFINITY),{min:r(t,i),max:r(e,s),minDefined:a(t),maxDefined:a(e)}}getMinMax(t){let e,{min:i,max:s,minDefined:n,maxDefined:o}=this.getUserBounds();if(n&&o)return{min:i,max:s};const a=this.getMatchingVisibleMetas();for(let r=0,l=a.length;r<l;++r)e=a[r].controller.getMinMax(this,t),n||(i=Math.min(i,e.min)),o||(s=Math.max(s,e.max));return i=o&&i>s?s:i,s=n&&i>s?i:s,{min:r(i,r(s,i)),max:r(s,r(i,s))}}getPadding(){return{left:this.paddingLeft||0,top:this.paddingTop||0,right:this.paddingRight||0,bottom:this.paddingBottom||0}}getTicks(){return this.ticks}getLabels(){const t=this.chart.data;return this.options.labels||(this.isHorizontal()?t.xLabels:t.yLabels)||t.labels||[]}getLabelItems(t=this.chart.chartArea){return this._labelItems||(this._labelItems=this._computeLabelItems(t))}beforeLayout(){this._cache={},this._dataLimitsCached=!1}beforeUpdate(){d(this.options.beforeUpdate,[this])}update(t,e,i){const{beginAtZero:s,grace:n,ticks:o}=this.options,a=o.sampleSize;this.beforeUpdate(),this.maxWidth=t,this.maxHeight=e,this._margins=i=Object.assign({left:0,right:0,top:0,bottom:0},i),this.ticks=null,this._labelSizes=null,this._gridLineItems=null,this._labelItems=null,this.beforeSetDimensions(),this.setDimensions(),this.afterSetDimensions(),this._maxLength=this.isHorizontal()?this.width+i.left+i.right:this.height+i.top+i.bottom,this._dataLimitsCached||(this.beforeDataLimits(),this.determineDataLimits(),this.afterDataLimits(),this._range=Di(this,n,s),this._dataLimitsCached=!0),this.beforeBuildTicks(),this.ticks=this.buildTicks()||[],this.afterBuildTicks();const r=a<this.ticks.length;this._convertTicksToLabels(r?Ks(this.ticks,a):this.ticks),this.configure(),this.beforeCalculateLabelRotation(),this.calculateLabelRotation(),this.afterCalculateLabelRotation(),o.display&&(o.autoSkip||"auto"===o.source)&&(this.ticks=Ys(this,this.ticks),this._labelSizes=null,this.afterAutoSkip()),r&&this._convertTicksToLabels(this.ticks),this.beforeFit(),this.fit(),this.afterFit(),this.afterUpdate()}configure(){let t,e,i=this.options.reverse;this.isHorizontal()?(t=this.left,e=this.right):(t=this.top,e=this.bottom,i=!i),this._startPixel=t,this._endPixel=e,this._reversePixels=i,this._length=e-t,this._alignToPixels=this.options.alignToPixels}afterUpdate(){d(this.options.afterUpdate,[this])}beforeSetDimensions(){d(this.options.beforeSetDimensions,[this])}setDimensions(){this.isHorizontal()?(this.width=this.maxWidth,this.left=0,this.right=this.width):(this.height=this.maxHeight,this.top=0,this.bottom=this.height),this.paddingLeft=0,this.paddingTop=0,this.paddingRight=0,this.paddingBottom=0}afterSetDimensions(){d(this.options.afterSetDimensions,[this])}_callHooks(t){this.chart.notifyPlugins(t,this.getContext()),d(this.options[t],[this])}beforeDataLimits(){this._callHooks("beforeDataLimits")}determineDataLimits(){}afterDataLimits(){this._callHooks("afterDataLimits")}beforeBuildTicks(){this._callHooks("beforeBuildTicks")}buildTicks(){return[]}afterBuildTicks(){this._callHooks("afterBuildTicks")}beforeTickToLabelConversion(){d(this.options.beforeTickToLabelConversion,[this])}generateTickLabels(t){const e=this.options.ticks;let i,s,n;for(i=0,s=t.length;i<s;i++)n=t[i],n.label=d(e.callback,[n.value,i,t],this)}afterTickToLabelConversion(){d(this.options.afterTickToLabelConversion,[this])}beforeCalculateLabelRotation(){d(this.options.beforeCalculateLabelRotation,[this])}calculateLabelRotation(){const t=this.options,e=t.ticks,i=qs(this.ticks.length,t.ticks.maxTicksLimit),s=e.minRotation||0,n=e.maxRotation;let o,a,r,l=s;if(!this._isVisible()||!e.display||s>=n||i<=1||!this.isHorizontal())return void(this.labelRotation=s);const h=this._getLabelSizes(),c=h.widest.width,d=h.highest.height,u=Z(this.chart.width-c,0,this.maxWidth);o=t.offset?this.maxWidth/i:u/(i-1),c+6>o&&(o=u/(i-(t.offset?.5:1)),a=this.maxHeight-Js(t.grid)-e.padding-Zs(t.title,this.chart.options.font),r=Math.sqrt(c*c+d*d),l=Y(Math.min(Math.asin(Z((h.highest.height+6)/o,-1,1)),Math.asin(Z(a/r,-1,1))-Math.asin(Z(d/r,-1,1)))),l=Math.max(s,Math.min(n,l))),this.labelRotation=l}afterCalculateLabelRotation(){d(this.options.afterCalculateLabelRotation,[this])}afterAutoSkip(){}beforeFit(){d(this.options.beforeFit,[this])}fit(){const t={width:0,height:0},{chart:e,options:{ticks:i,title:s,grid:n}}=this,o=this._isVisible(),a=this.isHorizontal();if(o){const o=Zs(s,e.options.font);if(a?(t.width=this.maxWidth,t.height=Js(n)+o):(t.height=this.maxHeight,t.width=Js(n)+o),i.display&&this.ticks.length){const{first:e,last:s,widest:n,highest:o}=this._getLabelSizes(),r=2*i.padding,l=$(this.labelRotation),h=Math.cos(l),c=Math.sin(l);if(a){const e=i.mirror?0:c*n.width+h*o.height;t.height=Math.min(this.maxHeight,t.height+e+r)}else{const e=i.mirror?0:h*n.width+c*o.height;t.width=Math.min(this.maxWidth,t.width+e+r)}this._calculatePadding(e,s,c,h)}}this._handleMargins(),a?(this.width=this._length=e.width-this._margins.left-this._margins.right,this.height=t.height):(this.width=t.width,this.height=this._length=e.height-this._margins.top-this._margins.bottom)}_calculatePadding(t,e,i,s){const{ticks:{align:n,padding:o},position:a}=this.options,r=0!==this.labelRotation,l="top"!==a&&"x"===this.axis;if(this.isHorizontal()){const a=this.getPixelForTick(0)-this.left,h=this.right-this.getPixelForTick(this.ticks.length-1);let c=0,d=0;r?l?(c=s*t.width,d=i*e.height):(c=i*t.height,d=s*e.width):"start"===n?d=e.width:"end"===n?c=t.width:"inner"!==n&&(c=t.width/2,d=e.width/2),this.paddingLeft=Math.max((c-a+o)*this.width/(this.width-a),0),this.paddingRight=Math.max((d-h+o)*this.width/(this.width-h),0)}else{let i=e.height/2,s=t.height/2;"start"===n?(i=0,s=t.height):"end"===n&&(i=e.height,s=0),this.paddingTop=i+o,this.paddingBottom=s+o}}_handleMargins(){this._margins&&(this._margins.left=Math.max(this.paddingLeft,this._margins.left),this._margins.top=Math.max(this.paddingTop,this._margins.top),this._margins.right=Math.max(this.paddingRight,this._margins.right),this._margins.bottom=Math.max(this.paddingBottom,this._margins.bottom))}afterFit(){d(this.options.afterFit,[this])}isHorizontal(){const{axis:t,position:e}=this.options;return"top"===e||"bottom"===e||"x"===t}isFullSize(){return this.options.fullSize}_convertTicksToLabels(t){let e,i;for(this.beforeTickToLabelConversion(),this.generateTickLabels(t),e=0,i=t.length;e<i;e++)s(t[e].label)&&(t.splice(e,1),i--,e--);this.afterTickToLabelConversion()}_getLabelSizes(){let t=this._labelSizes;if(!t){const e=this.options.ticks.sampleSize;let i=this.ticks;e<i.length&&(i=Ks(i,e)),this._labelSizes=t=this._computeLabelSizes(i,i.length,this.options.ticks.maxTicksLimit)}return t}_computeLabelSizes(t,e,i){const{ctx:o,_longestTextCache:a}=this,r=[],l=[],h=Math.floor(e/qs(e,i));let c,d,f,g,p,m,x,b,_,y,v,M=0,w=0;for(c=0;c<e;c+=h){if(g=t[c].label,p=this._resolveTickFontOptions(c),o.font=m=p.string,x=a[m]=a[m]||{data:{},gc:[]},b=p.lineHeight,_=y=0,s(g)||n(g)){if(n(g))for(d=0,f=g.length;d<f;++d)v=g[d],s(v)||n(v)||(_=Ce(o,x.data,x.gc,_,v),y+=b)}else _=Ce(o,x.data,x.gc,_,g),y=b;r.push(_),l.push(y),M=Math.max(_,M),w=Math.max(y,w)}!function(t,e){u(t,(t=>{const i=t.gc,s=i.length/2;let n;if(s>e){for(n=0;n<s;++n)delete t.data[i[n]];i.splice(0,s)}}))}(a,e);const k=r.indexOf(M),S=l.indexOf(w),P=t=>({width:r[t]||0,height:l[t]||0});return{first:P(0),last:P(e-1),widest:P(k),highest:P(S),widths:r,heights:l}}getLabelForValue(t){return t}getPixelForValue(t,e){return NaN}getValueForPixel(t){}getPixelForTick(t){const e=this.ticks;return t<0||t>e.length-1?null:this.getPixelForValue(e[t].value)}getPixelForDecimal(t){this._reversePixels&&(t=1-t);const e=this._startPixel+t*this._length;return Q(this._alignToPixels?Ae(this.chart,e,0):e)}getDecimalForPixel(t){const e=(t-this._startPixel)/this._length;return this._reversePixels?1-e:e}getBasePixel(){return this.getPixelForValue(this.getBaseValue())}getBaseValue(){const{min:t,max:e}=this;return t<0&&e<0?e:t>0&&e>0?t:0}getContext(t){const e=this.ticks||[];if(t>=0&&t<e.length){const i=e[t];return i.$context||(i.$context=function(t,e,i){return Ci(t,{tick:i,index:e,type:"tick"})}(this.getContext(),t,i))}return this.$context||(this.$context=Ci(this.chart.getContext(),{scale:this,type:"scale"}))}_tickSize(){const t=this.options.ticks,e=$(this.labelRotation),i=Math.abs(Math.cos(e)),s=Math.abs(Math.sin(e)),n=this._getLabelSizes(),o=t.autoSkipPadding||0,a=n?n.widest.width+o:0,r=n?n.highest.height+o:0;return this.isHorizontal()?r*i>a*s?a/i:r/s:r*s<a*i?r/i:a/s}_isVisible(){const t=this.options.display;return"auto"!==t?!!t:this.getMatchingVisibleMetas().length>0}_computeGridLineItems(t){const e=this.axis,i=this.chart,s=this.options,{grid:n,position:a,border:r}=s,h=n.offset,c=this.isHorizontal(),d=this.ticks.length+(h?1:0),u=Js(n),f=[],g=r.setContext(this.getContext()),p=g.display?g.width:0,m=p/2,x=function(t){return Ae(i,t,p)};let b,_,y,v,M,w,k,S,P,D,C,O;if("top"===a)b=x(this.bottom),w=this.bottom-u,S=b-m,D=x(t.top)+m,O=t.bottom;else if("bottom"===a)b=x(this.top),D=t.top,O=x(t.bottom)-m,w=b+m,S=this.top+u;else if("left"===a)b=x(this.right),M=this.right-u,k=b-m,P=x(t.left)+m,C=t.right;else if("right"===a)b=x(this.left),P=t.left,C=x(t.right)-m,M=b+m,k=this.left+u;else if("x"===e){if("center"===a)b=x((t.top+t.bottom)/2+.5);else if(o(a)){const t=Object.keys(a)[0],e=a[t];b=x(this.chart.scales[t].getPixelForValue(e))}D=t.top,O=t.bottom,w=b+m,S=w+u}else if("y"===e){if("center"===a)b=x((t.left+t.right)/2);else if(o(a)){const t=Object.keys(a)[0],e=a[t];b=x(this.chart.scales[t].getPixelForValue(e))}M=b-m,k=M-u,P=t.left,C=t.right}const A=l(s.ticks.maxTicksLimit,d),T=Math.max(1,Math.ceil(d/A));for(_=0;_<d;_+=T){const t=this.getContext(_),e=n.setContext(t),s=r.setContext(t),o=e.lineWidth,a=e.color,l=s.dash||[],d=s.dashOffset,u=e.tickWidth,g=e.tickColor,p=e.tickBorderDash||[],m=e.tickBorderDashOffset;y=Gs(this,_,h),void 0!==y&&(v=Ae(i,y,o),c?M=k=P=C=v:w=S=D=O=v,f.push({tx1:M,ty1:w,tx2:k,ty2:S,x1:P,y1:D,x2:C,y2:O,width:o,color:a,borderDash:l,borderDashOffset:d,tickWidth:u,tickColor:g,tickBorderDash:p,tickBorderDashOffset:m}))}return this._ticksLength=d,this._borderValue=b,f}_computeLabelItems(t){const e=this.axis,i=this.options,{position:s,ticks:a}=i,r=this.isHorizontal(),l=this.ticks,{align:h,crossAlign:c,padding:d,mirror:u}=a,f=Js(i.grid),g=f+d,p=u?-d:g,m=-$(this.labelRotation),x=[];let b,_,y,v,M,w,k,S,P,D,C,O,A="middle";if("top"===s)w=this.bottom-p,k=this._getXAxisLabelAlignment();else if("bottom"===s)w=this.top+p,k=this._getXAxisLabelAlignment();else if("left"===s){const t=this._getYAxisLabelAlignment(f);k=t.textAlign,M=t.x}else if("right"===s){const t=this._getYAxisLabelAlignment(f);k=t.textAlign,M=t.x}else if("x"===e){if("center"===s)w=(t.top+t.bottom)/2+g;else if(o(s)){const t=Object.keys(s)[0],e=s[t];w=this.chart.scales[t].getPixelForValue(e)+g}k=this._getXAxisLabelAlignment()}else if("y"===e){if("center"===s)M=(t.left+t.right)/2-g;else if(o(s)){const t=Object.keys(s)[0],e=s[t];M=this.chart.scales[t].getPixelForValue(e)}k=this._getYAxisLabelAlignment(f).textAlign}"y"===e&&("start"===h?A="top":"end"===h&&(A="bottom"));const T=this._getLabelSizes();for(b=0,_=l.length;b<_;++b){y=l[b],v=y.label;const t=a.setContext(this.getContext(b));S=this.getPixelForTick(b)+a.labelOffset,P=this._resolveTickFontOptions(b),D=P.lineHeight,C=n(v)?v.length:1;const e=C/2,i=t.color,o=t.textStrokeColor,h=t.textStrokeWidth;let d,f=k;if(r?(M=S,"inner"===k&&(f=b===_-1?this.options.reverse?"left":"right":0===b?this.options.reverse?"right":"left":"center"),O="top"===s?"near"===c||0!==m?-C*D+D/2:"center"===c?-T.highest.height/2-e*D+D:-T.highest.height+D/2:"near"===c||0!==m?D/2:"center"===c?T.highest.height/2-e*D:T.highest.height-C*D,u&&(O*=-1),0===m||t.showLabelBackdrop||(M+=D/2*Math.sin(m))):(w=S,O=(1-C)*D/2),t.showLabelBackdrop){const e=ki(t.backdropPadding),i=T.heights[b],s=T.widths[b];let n=O-e.top,o=0-e.left;switch(A){case"middle":n-=i/2;break;case"bottom":n-=i}switch(k){case"center":o-=s/2;break;case"right":o-=s;break;case"inner":b===_-1?o-=s:b>0&&(o-=s/2)}d={left:o,top:n,width:s+e.width,height:i+e.height,color:t.backdropColor}}x.push({label:v,font:P,textOffset:O,options:{rotation:m,color:i,strokeColor:o,strokeWidth:h,textAlign:f,textBaseline:A,translation:[M,w],backdrop:d}})}return x}_getXAxisLabelAlignment(){const{position:t,ticks:e}=this.options;if(-$(this.labelRotation))return"top"===t?"left":"right";let i="center";return"start"===e.align?i="left":"end"===e.align?i="right":"inner"===e.align&&(i="inner"),i}_getYAxisLabelAlignment(t){const{position:e,ticks:{crossAlign:i,mirror:s,padding:n}}=this.options,o=t+n,a=this._getLabelSizes().widest.width;let r,l;return"left"===e?s?(l=this.right+n,"near"===i?r="left":"center"===i?(r="center",l+=a/2):(r="right",l+=a)):(l=this.right-o,"near"===i?r="right":"center"===i?(r="center",l-=a/2):(r="left",l=this.left)):"right"===e?s?(l=this.left+n,"near"===i?r="right":"center"===i?(r="center",l-=a/2):(r="left",l-=a)):(l=this.left+o,"near"===i?r="left":"center"===i?(r="center",l+=a/2):(r="right",l=this.right)):r="right",{textAlign:r,x:l}}_computeLabelArea(){if(this.options.ticks.mirror)return;const t=this.chart,e=this.options.position;return"left"===e||"right"===e?{top:0,left:this.left,bottom:t.height,right:this.right}:"top"===e||"bottom"===e?{top:this.top,left:0,bottom:this.bottom,right:t.width}:void 0}drawBackground(){const{ctx:t,options:{backgroundColor:e},left:i,top:s,width:n,height:o}=this;e&&(t.save(),t.fillStyle=e,t.fillRect(i,s,n,o),t.restore())}getLineWidthForValue(t){const e=this.options.grid;if(!this._isVisible()||!e.display)return 0;const i=this.ticks.findIndex((e=>e.value===t));if(i>=0){return e.setContext(this.getContext(i)).lineWidth}return 0}drawGrid(t){const e=this.options.grid,i=this.ctx,s=this._gridLineItems||(this._gridLineItems=this._computeGridLineItems(t));let n,o;const a=(t,e,s)=>{s.width&&s.color&&(i.save(),i.lineWidth=s.width,i.strokeStyle=s.color,i.setLineDash(s.borderDash||[]),i.lineDashOffset=s.borderDashOffset,i.beginPath(),i.moveTo(t.x,t.y),i.lineTo(e.x,e.y),i.stroke(),i.restore())};if(e.display)for(n=0,o=s.length;n<o;++n){const t=s[n];e.drawOnChartArea&&a({x:t.x1,y:t.y1},{x:t.x2,y:t.y2},t),e.drawTicks&&a({x:t.tx1,y:t.ty1},{x:t.tx2,y:t.ty2},{color:t.tickColor,width:t.tickWidth,borderDash:t.tickBorderDash,borderDashOffset:t.tickBorderDashOffset})}}drawBorder(){const{chart:t,ctx:e,options:{border:i,grid:s}}=this,n=i.setContext(this.getContext()),o=i.display?n.width:0;if(!o)return;const a=s.setContext(this.getContext(0)).lineWidth,r=this._borderValue;let l,h,c,d;this.isHorizontal()?(l=Ae(t,this.left,o)-o/2,h=Ae(t,this.right,a)+a/2,c=d=r):(c=Ae(t,this.top,o)-o/2,d=Ae(t,this.bottom,a)+a/2,l=h=r),e.save(),e.lineWidth=n.width,e.strokeStyle=n.color,e.beginPath(),e.moveTo(l,c),e.lineTo(h,d),e.stroke(),e.restore()}drawLabels(t){if(!this.options.ticks.display)return;const e=this.ctx,i=this._computeLabelArea();i&&Ie(e,i);const s=this.getLabelItems(t);for(const t of s){const i=t.options,s=t.font;Ne(e,t.label,0,t.textOffset,s,i)}i&&ze(e)}drawTitle(){const{ctx:t,options:{position:e,title:i,reverse:s}}=this;if(!i.display)return;const a=Si(i.font),r=ki(i.padding),l=i.align;let h=a.lineHeight/2;"bottom"===e||"center"===e||o(e)?(h+=r.bottom,n(i.text)&&(h+=a.lineHeight*(i.text.length-1))):h+=r.top;const{titleX:c,titleY:d,maxWidth:u,rotation:f}=function(t,e,i,s){const{top:n,left:a,bottom:r,right:l,chart:h}=t,{chartArea:c,scales:d}=h;let u,f,g,p=0;const m=r-n,x=l-a;if(t.isHorizontal()){if(f=ft(s,a,l),o(i)){const t=Object.keys(i)[0],s=i[t];g=d[t].getPixelForValue(s)+m-e}else g="center"===i?(c.bottom+c.top)/2+m-e:Xs(t,i,e);u=l-a}else{if(o(i)){const t=Object.keys(i)[0],s=i[t];f=d[t].getPixelForValue(s)-x+e}else f="center"===i?(c.left+c.right)/2-x+e:Xs(t,i,e);g=ft(s,r,n),p="left"===i?-E:E}return{titleX:f,titleY:g,maxWidth:u,rotation:p}}(this,h,e,l);Ne(t,i.text,0,0,a,{color:i.color,maxWidth:u,rotation:f,textAlign:Qs(l,e,s),textBaseline:"middle",translation:[c,d]})}draw(t){this._isVisible()&&(this.drawBackground(),this.drawGrid(t),this.drawBorder(),this.drawTitle(),this.drawLabels(t))}_layers(){const t=this.options,e=t.ticks&&t.ticks.z||0,i=l(t.grid&&t.grid.z,-1),s=l(t.border&&t.border.z,0);return this._isVisible()&&this.draw===tn.prototype.draw?[{z:i,draw:t=>{this.drawBackground(),this.drawGrid(t),this.drawTitle()}},{z:s,draw:()=>{this.drawBorder()}},{z:e,draw:t=>{this.drawLabels(t)}}]:[{z:e,draw:t=>{this.draw(t)}}]}getMatchingVisibleMetas(t){const e=this.chart.getSortedVisibleDatasetMetas(),i=this.axis+"AxisID",s=[];let n,o;for(n=0,o=e.length;n<o;++n){const o=e[n];o[i]!==this.id||t&&o.type!==t||s.push(o)}return s}_resolveTickFontOptions(t){return Si(this.options.ticks.setContext(this.getContext(t)).font)}_maxDigits(){const t=this._resolveTickFontOptions(0).lineHeight;return(this.isHorizontal()?this.width:this.height)/t}}class en{constructor(t,e,i){this.type=t,this.scope=e,this.override=i,this.items=Object.create(null)}isForType(t){return Object.prototype.isPrototypeOf.call(this.type.prototype,t.prototype)}register(t){const e=Object.getPrototypeOf(t);let i;(function(t){return"id"in t&&"defaults"in t})(e)&&(i=this.register(e));const s=this.items,n=t.id,o=this.scope+"."+n;if(!n)throw new Error("class does not have id: "+t);return n in s||(s[n]=t,function(t,e,i){const s=x(Object.create(null),[i?ue.get(i):{},ue.get(e),t.defaults]);ue.set(e,s),t.defaultRoutes&&function(t,e){Object.keys(e).forEach((i=>{const s=i.split("."),n=s.pop(),o=[t].concat(s).join("."),a=e[i].split("."),r=a.pop(),l=a.join(".");ue.route(o,n,l,r)}))}(e,t.defaultRoutes);t.descriptors&&ue.describe(e,t.descriptors)}(t,o,i),this.override&&ue.override(t.id,t.overrides)),o}get(t){return this.items[t]}unregister(t){const e=this.items,i=t.id,s=this.scope;i in e&&delete e[i],s&&i in ue[s]&&(delete ue[s][i],this.override&&delete re[i])}}class sn{constructor(){this.controllers=new en(js,"datasets",!0),this.elements=new en($s,"elements"),this.plugins=new en(Object,"plugins"),this.scales=new en(tn,"scales"),this._typedRegistries=[this.controllers,this.scales,this.elements]}add(...t){this._each("register",t)}remove(...t){this._each("unregister",t)}addControllers(...t){this._each("register",t,this.controllers)}addElements(...t){this._each("register",t,this.elements)}addPlugins(...t){this._each("register",t,this.plugins)}addScales(...t){this._each("register",t,this.scales)}getController(t){return this._get(t,this.controllers,"controller")}getElement(t){return this._get(t,this.elements,"element")}getPlugin(t){return this._get(t,this.plugins,"plugin")}getScale(t){return this._get(t,this.scales,"scale")}removeControllers(...t){this._each("unregister",t,this.controllers)}removeElements(...t){this._each("unregister",t,this.elements)}removePlugins(...t){this._each("unregister",t,this.plugins)}removeScales(...t){this._each("unregister",t,this.scales)}_each(t,e,i){[...e].forEach((e=>{const s=i||this._getRegistryForType(e);i||s.isForType(e)||s===this.plugins&&e.id?this._exec(t,s,e):u(e,(e=>{const s=i||this._getRegistryForType(e);this._exec(t,s,e)}))}))}_exec(t,e,i){const s=w(t);d(i["before"+s],[],i),e[t](i),d(i["after"+s],[],i)}_getRegistryForType(t){for(let e=0;e<this._typedRegistries.length;e++){const i=this._typedRegistries[e];if(i.isForType(t))return i}return this.plugins}_get(t,e,i){const s=e.get(t);if(void 0===s)throw new Error('"'+t+'" is not a registered '+i+".");return s}}var nn=new sn;class on{constructor(){this._init=void 0}notify(t,e,i,s){if("beforeInit"===e&&(this._init=this._createDescriptors(t,!0),this._notify(this._init,t,"install")),void 0===this._init)return;const n=s?this._descriptors(t).filter(s):this._descriptors(t),o=this._notify(n,t,e,i);return"afterDestroy"===e&&(this._notify(n,t,"stop"),this._notify(this._init,t,"uninstall"),this._init=void 0),o}_notify(t,e,i,s){s=s||{};for(const n of t){const t=n.plugin;if(!1===d(t[i],[e,s,n.options],t)&&s.cancelable)return!1}return!0}invalidate(){s(this._cache)||(this._oldCache=this._cache,this._cache=void 0)}_descriptors(t){if(this._cache)return this._cache;const e=this._cache=this._createDescriptors(t);return this._notifyStateChanges(t),e}_createDescriptors(t,e){const i=t&&t.config,s=l(i.options&&i.options.plugins,{}),n=function(t){const e={},i=[],s=Object.keys(nn.plugins.items);for(let t=0;t<s.length;t++)i.push(nn.getPlugin(s[t]));const n=t.plugins||[];for(let t=0;t<n.length;t++){const s=n[t];-1===i.indexOf(s)&&(i.push(s),e[s.id]=!0)}return{plugins:i,localIds:e}}(i);return!1!==s||e?function(t,{plugins:e,localIds:i},s,n){const o=[],a=t.getContext();for(const r of e){const e=r.id,l=an(s[e],n);null!==l&&o.push({plugin:r,options:rn(t.config,{plugin:r,local:i[e]},l,a)})}return o}(t,n,s,e):[]}_notifyStateChanges(t){const e=this._oldCache||[],i=this._cache,s=(t,e)=>t.filter((t=>!e.some((e=>t.plugin.id===e.plugin.id))));this._notify(s(e,i),t,"stop"),this._notify(s(i,e),t,"start")}}function an(t,e){return e||!1!==t?!0===t?{}:t:null}function rn(t,{plugin:e,local:i},s,n){const o=t.pluginScopeKeys(e),a=t.getOptionScopes(s,o);return i&&e.defaults&&a.push(e.defaults),t.createResolver(a,n,[""],{scriptable:!1,indexable:!1,allKeys:!0})}function ln(t,e){const i=ue.datasets[t]||{};return((e.datasets||{})[t]||{}).indexAxis||e.indexAxis||i.indexAxis||"x"}function hn(t){if("x"===t||"y"===t||"r"===t)return t}function cn(t,...e){if(hn(t))return t;for(const s of e){const e=s.axis||("top"===(i=s.position)||"bottom"===i?"x":"left"===i||"right"===i?"y":void 0)||t.length>1&&hn(t[0].toLowerCase());if(e)return e}var i;throw new Error(`Cannot determine type of '${t}' axis. Please provide 'axis' or 'position' option.`)}function dn(t,e,i){if(i[e+"AxisID"]===t)return{axis:e}}function un(t,e){const i=re[t.type]||{scales:{}},s=e.scales||{},n=ln(t.type,e),a=Object.create(null);return Object.keys(s).forEach((e=>{const r=s[e];if(!o(r))return console.error(`Invalid scale configuration for scale: ${e}`);if(r._proxy)return console.warn(`Ignoring resolver passed as options for scale: ${e}`);const l=cn(e,r,function(t,e){if(e.data&&e.data.datasets){const i=e.data.datasets.filter((e=>e.xAxisID===t||e.yAxisID===t));if(i.length)return dn(t,"x",i[0])||dn(t,"y",i[0])}return{}}(e,t),ue.scales[r.type]),h=function(t,e){return t===e?"_index_":"_value_"}(l,n),c=i.scales||{};a[e]=b(Object.create(null),[{axis:l},r,c[l],c[h]])})),t.data.datasets.forEach((i=>{const n=i.type||t.type,o=i.indexAxis||ln(n,e),r=(re[n]||{}).scales||{};Object.keys(r).forEach((t=>{const e=function(t,e){let i=t;return"_index_"===t?i=e:"_value_"===t&&(i="x"===e?"y":"x"),i}(t,o),n=i[e+"AxisID"]||e;a[n]=a[n]||Object.create(null),b(a[n],[{axis:e},s[n],r[t]])}))})),Object.keys(a).forEach((t=>{const e=a[t];b(e,[ue.scales[e.type],ue.scale])})),a}function fn(t){const e=t.options||(t.options={});e.plugins=l(e.plugins,{}),e.scales=un(t,e)}function gn(t){return(t=t||{}).datasets=t.datasets||[],t.labels=t.labels||[],t}const pn=new Map,mn=new Set;function xn(t,e){let i=pn.get(t);return i||(i=e(),pn.set(t,i),mn.add(i)),i}const bn=(t,e,i)=>{const s=M(e,i);void 0!==s&&t.add(s)};class _n{constructor(t){this._config=function(t){return(t=t||{}).data=gn(t.data),fn(t),t}(t),this._scopeCache=new Map,this._resolverCache=new Map}get platform(){return this._config.platform}get type(){return this._config.type}set type(t){this._config.type=t}get data(){return this._config.data}set data(t){this._config.data=gn(t)}get options(){return this._config.options}set options(t){this._config.options=t}get plugins(){return this._config.plugins}update(){const t=this._config;this.clearCache(),fn(t)}clearCache(){this._scopeCache.clear(),this._resolverCache.clear()}datasetScopeKeys(t){return xn(t,(()=>[[`datasets.${t}`,""]]))}datasetAnimationScopeKeys(t,e){return xn(`${t}.transition.${e}`,(()=>[[`datasets.${t}.transitions.${e}`,`transitions.${e}`],[`datasets.${t}`,""]]))}datasetElementScopeKeys(t,e){return xn(`${t}-${e}`,(()=>[[`datasets.${t}.elements.${e}`,`datasets.${t}`,`elements.${e}`,""]]))}pluginScopeKeys(t){const e=t.id;return xn(`${this.type}-plugin-${e}`,(()=>[[`plugins.${e}`,...t.additionalOptionScopes||[]]]))}_cachedScopes(t,e){const i=this._scopeCache;let s=i.get(t);return s&&!e||(s=new Map,i.set(t,s)),s}getOptionScopes(t,e,i){const{options:s,type:n}=this,o=this._cachedScopes(t,i),a=o.get(e);if(a)return a;const r=new Set;e.forEach((e=>{t&&(r.add(t),e.forEach((e=>bn(r,t,e)))),e.forEach((t=>bn(r,s,t))),e.forEach((t=>bn(r,re[n]||{},t))),e.forEach((t=>bn(r,ue,t))),e.forEach((t=>bn(r,le,t)))}));const l=Array.from(r);return 0===l.length&&l.push(Object.create(null)),mn.has(e)&&o.set(e,l),l}chartOptionScopes(){const{options:t,type:e}=this;return[t,re[e]||{},ue.datasets[e]||{},{type:e},ue,le]}resolveNamedOptions(t,e,i,s=[""]){const o={$shared:!0},{resolver:a,subPrefixes:r}=yn(this._resolverCache,t,s);let l=a;if(function(t,e){const{isScriptable:i,isIndexable:s}=Ye(t);for(const o of e){const e=i(o),a=s(o),r=(a||e)&&t[o];if(e&&(S(r)||vn(r))||a&&n(r))return!0}return!1}(a,e)){o.$shared=!1;l=$e(a,i=S(i)?i():i,this.createResolver(t,i,r))}for(const t of e)o[t]=l[t];return o}createResolver(t,e,i=[""],s){const{resolver:n}=yn(this._resolverCache,t,i);return o(e)?$e(n,e,void 0,s):n}}function yn(t,e,i){let s=t.get(e);s||(s=new Map,t.set(e,s));const n=i.join();let o=s.get(n);if(!o){o={resolver:je(e,i),subPrefixes:i.filter((t=>!t.toLowerCase().includes("hover")))},s.set(n,o)}return o}const vn=t=>o(t)&&Object.getOwnPropertyNames(t).some((e=>S(t[e])));const Mn=["top","bottom","left","right","chartArea"];function wn(t,e){return"top"===t||"bottom"===t||-1===Mn.indexOf(t)&&"x"===e}function kn(t,e){return function(i,s){return i[t]===s[t]?i[e]-s[e]:i[t]-s[t]}}function Sn(t){const e=t.chart,i=e.options.animation;e.notifyPlugins("afterRender"),d(i&&i.onComplete,[t],e)}function Pn(t){const e=t.chart,i=e.options.animation;d(i&&i.onProgress,[t],e)}function Dn(t){return fe()&&"string"==typeof t?t=document.getElementById(t):t&&t.length&&(t=t[0]),t&&t.canvas&&(t=t.canvas),t}const Cn={},On=t=>{const e=Dn(t);return Object.values(Cn).filter((t=>t.canvas===e)).pop()};function An(t,e,i){const s=Object.keys(t);for(const n of s){const s=+n;if(s>=e){const o=t[n];delete t[n],(i>0||s>e)&&(t[s+i]=o)}}}class Tn{static defaults=ue;static instances=Cn;static overrides=re;static registry=nn;static version="4.5.1";static getChart=On;static register(...t){nn.add(...t),Ln()}static unregister(...t){nn.remove(...t),Ln()}constructor(t,e){const s=this.config=new _n(e),n=Dn(t),o=On(n);if(o)throw new Error("Canvas is already in use. Chart with ID '"+o.id+"' must be destroyed before the canvas with ID '"+o.canvas.id+"' can be reused.");const a=s.createResolver(s.chartOptionScopes(),this.getContext());this.platform=new(s.platform||Ps(n)),this.platform.updateConfig(s);const r=this.platform.acquireContext(n,a.aspectRatio),l=r&&r.canvas,h=l&&l.height,c=l&&l.width;this.id=i(),this.ctx=r,this.canvas=l,this.width=c,this.height=h,this._options=a,this._aspectRatio=this.aspectRatio,this._layers=[],this._metasets=[],this._stacks=void 0,this.boxes=[],this.currentDevicePixelRatio=void 0,this.chartArea=void 0,this._active=[],this._lastEvent=void 0,this._listeners={},this._responsiveListeners=void 0,this._sortedMetasets=[],this.scales={},this._plugins=new on,this.$proxies={},this._hiddenIndices={},this.attached=!1,this._animationsDisabled=void 0,this.$context=void 0,this._doResize=dt((t=>this.update(t)),a.resizeDelay||0),this._dataChanges=[],Cn[this.id]=this,r&&l?(bt.listen(this,"complete",Sn),bt.listen(this,"progress",Pn),this._initialize(),this.attached&&this.update()):console.error("Failed to create chart: can't acquire context from the given item")}get aspectRatio(){const{options:{aspectRatio:t,maintainAspectRatio:e},width:i,height:n,_aspectRatio:o}=this;return s(t)?e&&o?o:n?i/n:null:t}get data(){return this.config.data}set data(t){this.config.data=t}get options(){return this._options}set options(t){this.config.options=t}get registry(){return nn}_initialize(){return this.notifyPlugins("beforeInit"),this.options.responsive?this.resize():ke(this,this.options.devicePixelRatio),this.bindEvents(),this.notifyPlugins("afterInit"),this}clear(){return Te(this.canvas,this.ctx),this}stop(){return bt.stop(this),this}resize(t,e){bt.running(this)?this._resizeBeforeDraw={width:t,height:e}:this._resize(t,e)}_resize(t,e){const i=this.options,s=this.canvas,n=i.maintainAspectRatio&&this.aspectRatio,o=this.platform.getMaximumSize(s,t,e,n),a=i.devicePixelRatio||this.platform.getDevicePixelRatio(),r=this.width?"resize":"attach";this.width=o.width,this.height=o.height,this._aspectRatio=this.aspectRatio,ke(this,a,!0)&&(this.notifyPlugins("resize",{size:o}),d(i.onResize,[this,o],this),this.attached&&this._doResize(r)&&this.render())}ensureScalesHaveIDs(){u(this.options.scales||{},((t,e)=>{t.id=e}))}buildOrUpdateScales(){const t=this.options,e=t.scales,i=this.scales,s=Object.keys(i).reduce(((t,e)=>(t[e]=!1,t)),{});let n=[];e&&(n=n.concat(Object.keys(e).map((t=>{const i=e[t],s=cn(t,i),n="r"===s,o="x"===s;return{options:i,dposition:n?"chartArea":o?"bottom":"left",dtype:n?"radialLinear":o?"category":"linear"}})))),u(n,(e=>{const n=e.options,o=n.id,a=cn(o,n),r=l(n.type,e.dtype);void 0!==n.position&&wn(n.position,a)===wn(e.dposition)||(n.position=e.dposition),s[o]=!0;let h=null;if(o in i&&i[o].type===r)h=i[o];else{h=new(nn.getScale(r))({id:o,type:r,ctx:this.ctx,chart:this}),i[h.id]=h}h.init(n,t)})),u(s,((t,e)=>{t||delete i[e]})),u(i,(t=>{ls.configure(this,t,t.options),ls.addBox(this,t)}))}_updateMetasets(){const t=this._metasets,e=this.data.datasets.length,i=t.length;if(t.sort(((t,e)=>t.index-e.index)),i>e){for(let t=e;t<i;++t)this._destroyDatasetMeta(t);t.splice(e,i-e)}this._sortedMetasets=t.slice(0).sort(kn("order","index"))}_removeUnreferencedMetasets(){const{_metasets:t,data:{datasets:e}}=this;t.length>e.length&&delete this._stacks,t.forEach(((t,i)=>{0===e.filter((e=>e===t._dataset)).length&&this._destroyDatasetMeta(i)}))}buildOrUpdateControllers(){const t=[],e=this.data.datasets;let i,s;for(this._removeUnreferencedMetasets(),i=0,s=e.length;i<s;i++){const s=e[i];let n=this.getDatasetMeta(i);const o=s.type||this.config.type;if(n.type&&n.type!==o&&(this._destroyDatasetMeta(i),n=this.getDatasetMeta(i)),n.type=o,n.indexAxis=s.indexAxis||ln(o,this.options),n.order=s.order||0,n.index=i,n.label=""+s.label,n.visible=this.isDatasetVisible(i),n.controller)n.controller.updateIndex(i),n.controller.linkScales();else{const e=nn.getController(o),{datasetElementType:s,dataElementType:a}=ue.datasets[o];Object.assign(e,{dataElementType:nn.getElement(a),datasetElementType:s&&nn.getElement(s)}),n.controller=new e(this,i),t.push(n.controller)}}return this._updateMetasets(),t}_resetElements(){u(this.data.datasets,((t,e)=>{this.getDatasetMeta(e).controller.reset()}),this)}reset(){this._resetElements(),this.notifyPlugins("reset")}update(t){const e=this.config;e.update();const i=this._options=e.createResolver(e.chartOptionScopes(),this.getContext()),s=this._animationsDisabled=!i.animation;if(this._updateScales(),this._checkEventBindings(),this._updateHiddenIndices(),this._plugins.invalidate(),!1===this.notifyPlugins("beforeUpdate",{mode:t,cancelable:!0}))return;const n=this.buildOrUpdateControllers();this.notifyPlugins("beforeElementsUpdate");let o=0;for(let t=0,e=this.data.datasets.length;t<e;t++){const{controller:e}=this.getDatasetMeta(t),i=!s&&-1===n.indexOf(e);e.buildOrUpdateElements(i),o=Math.max(+e.getMaxOverflow(),o)}o=this._minPadding=i.layout.autoPadding?o:0,this._updateLayout(o),s||u(n,(t=>{t.reset()})),this._updateDatasets(t),this.notifyPlugins("afterUpdate",{mode:t}),this._layers.sort(kn("z","_idx"));const{_active:a,_lastEvent:r}=this;r?this._eventHandler(r,!0):a.length&&this._updateHoverStyles(a,a,!0),this.render()}_updateScales(){u(this.scales,(t=>{ls.removeBox(this,t)})),this.ensureScalesHaveIDs(),this.buildOrUpdateScales()}_checkEventBindings(){const t=this.options,e=new Set(Object.keys(this._listeners)),i=new Set(t.events);P(e,i)&&!!this._responsiveListeners===t.responsive||(this.unbindEvents(),this.bindEvents())}_updateHiddenIndices(){const{_hiddenIndices:t}=this,e=this._getUniformDataChanges()||[];for(const{method:i,start:s,count:n}of e){An(t,s,"_removeElements"===i?-n:n)}}_getUniformDataChanges(){const t=this._dataChanges;if(!t||!t.length)return;this._dataChanges=[];const e=this.data.datasets.length,i=e=>new Set(t.filter((t=>t[0]===e)).map(((t,e)=>e+","+t.splice(1).join(",")))),s=i(0);for(let t=1;t<e;t++)if(!P(s,i(t)))return;return Array.from(s).map((t=>t.split(","))).map((t=>({method:t[1],start:+t[2],count:+t[3]})))}_updateLayout(t){if(!1===this.notifyPlugins("beforeLayout",{cancelable:!0}))return;ls.update(this,this.width,this.height,t);const e=this.chartArea,i=e.width<=0||e.height<=0;this._layers=[],u(this.boxes,(t=>{i&&"chartArea"===t.position||(t.configure&&t.configure(),this._layers.push(...t._layers()))}),this),this._layers.forEach(((t,e)=>{t._idx=e})),this.notifyPlugins("afterLayout")}_updateDatasets(t){if(!1!==this.notifyPlugins("beforeDatasetsUpdate",{mode:t,cancelable:!0})){for(let t=0,e=this.data.datasets.length;t<e;++t)this.getDatasetMeta(t).controller.configure();for(let e=0,i=this.data.datasets.length;e<i;++e)this._updateDataset(e,S(t)?t({datasetIndex:e}):t);this.notifyPlugins("afterDatasetsUpdate",{mode:t})}}_updateDataset(t,e){const i=this.getDatasetMeta(t),s={meta:i,index:t,mode:e,cancelable:!0};!1!==this.notifyPlugins("beforeDatasetUpdate",s)&&(i.controller._update(e),s.cancelable=!1,this.notifyPlugins("afterDatasetUpdate",s))}render(){!1!==this.notifyPlugins("beforeRender",{cancelable:!0})&&(bt.has(this)?this.attached&&!bt.running(this)&&bt.start(this):(this.draw(),Sn({chart:this})))}draw(){let t;if(this._resizeBeforeDraw){const{width:t,height:e}=this._resizeBeforeDraw;this._resizeBeforeDraw=null,this._resize(t,e)}if(this.clear(),this.width<=0||this.height<=0)return;if(!1===this.notifyPlugins("beforeDraw",{cancelable:!0}))return;const e=this._layers;for(t=0;t<e.length&&e[t].z<=0;++t)e[t].draw(this.chartArea);for(this._drawDatasets();t<e.length;++t)e[t].draw(this.chartArea);this.notifyPlugins("afterDraw")}_getSortedDatasetMetas(t){const e=this._sortedMetasets,i=[];let s,n;for(s=0,n=e.length;s<n;++s){const n=e[s];t&&!n.visible||i.push(n)}return i}getSortedVisibleDatasetMetas(){return this._getSortedDatasetMetas(!0)}_drawDatasets(){if(!1===this.notifyPlugins("beforeDatasetsDraw",{cancelable:!0}))return;const t=this.getSortedVisibleDatasetMetas();for(let e=t.length-1;e>=0;--e)this._drawDataset(t[e]);this.notifyPlugins("afterDatasetsDraw")}_drawDataset(t){const e=this.ctx,i={meta:t,index:t.index,cancelable:!0},s=Ni(this,t);!1!==this.notifyPlugins("beforeDatasetDraw",i)&&(s&&Ie(e,s),t.controller.draw(),s&&ze(e),i.cancelable=!1,this.notifyPlugins("afterDatasetDraw",i))}isPointInArea(t){return Re(t,this.chartArea,this._minPadding)}getElementsAtEventForMode(t,e,i,s){const n=Ki.modes[e];return"function"==typeof n?n(this,t,i,s):[]}getDatasetMeta(t){const e=this.data.datasets[t],i=this._metasets;let s=i.filter((t=>t&&t._dataset===e)).pop();return s||(s={type:null,data:[],dataset:null,controller:null,hidden:null,xAxisID:null,yAxisID:null,order:e&&e.order||0,index:t,_dataset:e,_parsed:[],_sorted:!1},i.push(s)),s}getContext(){return this.$context||(this.$context=Ci(null,{chart:this,type:"chart"}))}getVisibleDatasetCount(){return this.getSortedVisibleDatasetMetas().length}isDatasetVisible(t){const e=this.data.datasets[t];if(!e)return!1;const i=this.getDatasetMeta(t);return"boolean"==typeof i.hidden?!i.hidden:!e.hidden}setDatasetVisibility(t,e){this.getDatasetMeta(t).hidden=!e}toggleDataVisibility(t){this._hiddenIndices[t]=!this._hiddenIndices[t]}getDataVisibility(t){return!this._hiddenIndices[t]}_updateVisibility(t,e,i){const s=i?"show":"hide",n=this.getDatasetMeta(t),o=n.controller._resolveAnimations(void 0,s);k(e)?(n.data[e].hidden=!i,this.update()):(this.setDatasetVisibility(t,i),o.update(n,{visible:i}),this.update((e=>e.datasetIndex===t?s:void 0)))}hide(t,e){this._updateVisibility(t,e,!1)}show(t,e){this._updateVisibility(t,e,!0)}_destroyDatasetMeta(t){const e=this._metasets[t];e&&e.controller&&e.controller._destroy(),delete this._metasets[t]}_stop(){let t,e;for(this.stop(),bt.remove(this),t=0,e=this.data.datasets.length;t<e;++t)this._destroyDatasetMeta(t)}destroy(){this.notifyPlugins("beforeDestroy");const{canvas:t,ctx:e}=this;this._stop(),this.config.clearCache(),t&&(this.unbindEvents(),Te(t,e),this.platform.releaseContext(e),this.canvas=null,this.ctx=null),delete Cn[this.id],this.notifyPlugins("afterDestroy")}toBase64Image(...t){return this.canvas.toDataURL(...t)}bindEvents(){this.bindUserEvents(),this.options.responsive?this.bindResponsiveEvents():this.attached=!0}bindUserEvents(){const t=this._listeners,e=this.platform,i=(i,s)=>{e.addEventListener(this,i,s),t[i]=s},s=(t,e,i)=>{t.offsetX=e,t.offsetY=i,this._eventHandler(t)};u(this.options.events,(t=>i(t,s)))}bindResponsiveEvents(){this._responsiveListeners||(this._responsiveListeners={});const t=this._responsiveListeners,e=this.platform,i=(i,s)=>{e.addEventListener(this,i,s),t[i]=s},s=(i,s)=>{t[i]&&(e.removeEventListener(this,i,s),delete t[i])},n=(t,e)=>{this.canvas&&this.resize(t,e)};let o;const a=()=>{s("attach",a),this.attached=!0,this.resize(),i("resize",n),i("detach",o)};o=()=>{this.attached=!1,s("resize",n),this._stop(),this._resize(0,0),i("attach",a)},e.isAttached(this.canvas)?a():o()}unbindEvents(){u(this._listeners,((t,e)=>{this.platform.removeEventListener(this,e,t)})),this._listeners={},u(this._responsiveListeners,((t,e)=>{this.platform.removeEventListener(this,e,t)})),this._responsiveListeners=void 0}updateHoverStyle(t,e,i){const s=i?"set":"remove";let n,o,a,r;for("dataset"===e&&(n=this.getDatasetMeta(t[0].datasetIndex),n.controller["_"+s+"DatasetHoverStyle"]()),a=0,r=t.length;a<r;++a){o=t[a];const e=o&&this.getDatasetMeta(o.datasetIndex).controller;e&&e[s+"HoverStyle"](o.element,o.datasetIndex,o.index)}}getActiveElements(){return this._active||[]}setActiveElements(t){const e=this._active||[],i=t.map((({datasetIndex:t,index:e})=>{const i=this.getDatasetMeta(t);if(!i)throw new Error("No dataset found at index "+t);return{datasetIndex:t,element:i.data[e],index:e}}));!f(i,e)&&(this._active=i,this._lastEvent=null,this._updateHoverStyles(i,e))}notifyPlugins(t,e,i){return this._plugins.notify(this,t,e,i)}isPluginEnabled(t){return 1===this._plugins._cache.filter((e=>e.plugin.id===t)).length}_updateHoverStyles(t,e,i){const s=this.options.hover,n=(t,e)=>t.filter((t=>!e.some((e=>t.datasetIndex===e.datasetIndex&&t.index===e.index)))),o=n(e,t),a=i?t:n(t,e);o.length&&this.updateHoverStyle(o,s.mode,!1),a.length&&s.mode&&this.updateHoverStyle(a,s.mode,!0)}_eventHandler(t,e){const i={event:t,replay:e,cancelable:!0,inChartArea:this.isPointInArea(t)},s=e=>(e.options.events||this.options.events).includes(t.native.type);if(!1===this.notifyPlugins("beforeEvent",i,s))return;const n=this._handleEvent(t,e,i.inChartArea);return i.cancelable=!1,this.notifyPlugins("afterEvent",i,s),(n||i.changed)&&this.render(),this}_handleEvent(t,e,i){const{_active:s=[],options:n}=this,o=e,a=this._getActiveElements(t,s,i,o),r=D(t),l=function(t,e,i,s){return i&&"mouseout"!==t.type?s?e:t:null}(t,this._lastEvent,i,r);i&&(this._lastEvent=null,d(n.onHover,[t,a,this],this),r&&d(n.onClick,[t,a,this],this));const h=!f(a,s);return(h||e)&&(this._active=a,this._updateHoverStyles(a,s,e)),this._lastEvent=l,h}_getActiveElements(t,e,i,s){if("mouseout"===t.type)return[];if(!i)return e;const n=this.options.hover;return this.getElementsAtEventForMode(t,n.mode,n,s)}}function Ln(){return u(Tn.instances,(t=>t._plugins.invalidate()))}function En(){throw new Error("This method is not implemented: Check that a complete date adapter is provided.")}class Rn{static override(t){Object.assign(Rn.prototype,t)}options;constructor(t){this.options=t||{}}init(){}formats(){return En()}parse(){return En()}format(){return En()}add(){return En()}diff(){return En()}startOf(){return En()}endOf(){return En()}}var In={_date:Rn};function zn(t){const e=t.iScale,i=function(t,e){if(!t._cache.$bar){const i=t.getMatchingVisibleMetas(e);let s=[];for(let e=0,n=i.length;e<n;e++)s=s.concat(i[e].controller.getAllParsedValues(t));t._cache.$bar=lt(s.sort(((t,e)=>t-e)))}return t._cache.$bar}(e,t.type);let s,n,o,a,r=e._length;const l=()=>{32767!==o&&-32768!==o&&(k(a)&&(r=Math.min(r,Math.abs(o-a)||r)),a=o)};for(s=0,n=i.length;s<n;++s)o=e.getPixelForValue(i[s]),l();for(a=void 0,s=0,n=e.ticks.length;s<n;++s)o=e.getPixelForTick(s),l();return r}function Fn(t,e,i,s){return n(t)?function(t,e,i,s){const n=i.parse(t[0],s),o=i.parse(t[1],s),a=Math.min(n,o),r=Math.max(n,o);let l=a,h=r;Math.abs(a)>Math.abs(r)&&(l=r,h=a),e[i.axis]=h,e._custom={barStart:l,barEnd:h,start:n,end:o,min:a,max:r}}(t,e,i,s):e[i.axis]=i.parse(t,s),e}function Vn(t,e,i,s){const n=t.iScale,o=t.vScale,a=n.getLabels(),r=n===o,l=[];let h,c,d,u;for(h=i,c=i+s;h<c;++h)u=e[h],d={},d[n.axis]=r||n.parse(a[h],h),l.push(Fn(u,d,o,h));return l}function Bn(t){return t&&void 0!==t.barStart&&void 0!==t.barEnd}function Wn(t,e,i,s){let n=e.borderSkipped;const o={};if(!n)return void(t.borderSkipped=o);if(!0===n)return void(t.borderSkipped={top:!0,right:!0,bottom:!0,left:!0});const{start:a,end:r,reverse:l,top:h,bottom:c}=function(t){let e,i,s,n,o;return t.horizontal?(e=t.base>t.x,i="left",s="right"):(e=t.base<t.y,i="bottom",s="top"),e?(n="end",o="start"):(n="start",o="end"),{start:i,end:s,reverse:e,top:n,bottom:o}}(t);"middle"===n&&i&&(t.enableBorderRadius=!0,(i._top||0)===s?n=h:(i._bottom||0)===s?n=c:(o[Nn(c,a,r,l)]=!0,n=h)),o[Nn(n,a,r,l)]=!0,t.borderSkipped=o}function Nn(t,e,i,s){var n,o,a;return s?(a=i,t=Hn(t=(n=t)===(o=e)?a:n===a?o:n,i,e)):t=Hn(t,e,i),t}function Hn(t,e,i){return"start"===t?e:"end"===t?i:t}function jn(t,{inflateAmount:e},i){t.inflateAmount="auto"===e?1===i?.33:0:e}class $n extends js{static id="doughnut";static defaults={datasetElementType:!1,dataElementType:"arc",animation:{animateRotate:!0,animateScale:!1},animations:{numbers:{type:"number",properties:["circumference","endAngle","innerRadius","outerRadius","startAngle","x","y","offset","borderWidth","spacing"]}},cutout:"50%",rotation:0,circumference:360,radius:"100%",spacing:0,indexAxis:"r"};static descriptors={_scriptable:t=>"spacing"!==t,_indexable:t=>"spacing"!==t&&!t.startsWith("borderDash")&&!t.startsWith("hoverBorderDash")};static overrides={aspectRatio:1,plugins:{legend:{labels:{generateLabels(t){const e=t.data,{labels:{pointStyle:i,textAlign:s,color:n,useBorderRadius:o,borderRadius:a}}=t.legend.options;return e.labels.length&&e.datasets.length?e.labels.map(((e,r)=>{const l=t.getDatasetMeta(0).controller.getStyle(r);return{text:e,fillStyle:l.backgroundColor,fontColor:n,hidden:!t.getDataVisibility(r),lineDash:l.borderDash,lineDashOffset:l.borderDashOffset,lineJoin:l.borderJoinStyle,lineWidth:l.borderWidth,strokeStyle:l.borderColor,textAlign:s,pointStyle:i,borderRadius:o&&(a||l.borderRadius),index:r}})):[]}},onClick(t,e,i){i.chart.toggleDataVisibility(e.index),i.chart.update()}}}};constructor(t,e){super(t,e),this.enableOptionSharing=!0,this.innerRadius=void 0,this.outerRadius=void 0,this.offsetX=void 0,this.offsetY=void 0}linkScales(){}parse(t,e){const i=this.getDataset().data,s=this._cachedMeta;if(!1===this._parsing)s._parsed=i;else{let n,a,r=t=>+i[t];if(o(i[t])){const{key:t="value"}=this._parsing;r=e=>+M(i[e],t)}for(n=t,a=t+e;n<a;++n)s._parsed[n]=r(n)}}_getRotation(){return $(this.options.rotation-90)}_getCircumference(){return $(this.options.circumference)}_getRotationExtents(){let t=O,e=-O;for(let i=0;i<this.chart.data.datasets.length;++i)if(this.chart.isDatasetVisible(i)&&this.chart.getDatasetMeta(i).type===this._type){const s=this.chart.getDatasetMeta(i).controller,n=s._getRotation(),o=s._getCircumference();t=Math.min(t,n),e=Math.max(e,n+o)}return{rotation:t,circumference:e-t}}update(t){const e=this.chart,{chartArea:i}=e,s=this._cachedMeta,n=s.data,o=this.getMaxBorderWidth()+this.getMaxOffset(n)+this.options.spacing,a=Math.max((Math.min(i.width,i.height)-o)/2,0),r=Math.min(h(this.options.cutout,a),1),l=this._getRingWeight(this.index),{circumference:d,rotation:u}=this._getRotationExtents(),{ratioX:f,ratioY:g,offsetX:p,offsetY:m}=function(t,e,i){let s=1,n=1,o=0,a=0;if(e<O){const r=t,l=r+e,h=Math.cos(r),c=Math.sin(r),d=Math.cos(l),u=Math.sin(l),f=(t,e,s)=>J(t,r,l,!0)?1:Math.max(e,e*i,s,s*i),g=(t,e,s)=>J(t,r,l,!0)?-1:Math.min(e,e*i,s,s*i),p=f(0,h,d),m=f(E,c,u),x=g(C,h,d),b=g(C+E,c,u);s=(p-x)/2,n=(m-b)/2,o=-(p+x)/2,a=-(m+b)/2}return{ratioX:s,ratioY:n,offsetX:o,offsetY:a}}(u,d,r),x=(i.width-o)/f,b=(i.height-o)/g,_=Math.max(Math.min(x,b)/2,0),y=c(this.options.radius,_),v=(y-Math.max(y*r,0))/this._getVisibleDatasetWeightTotal();this.offsetX=p*y,this.offsetY=m*y,s.total=this.calculateTotal(),this.outerRadius=y-v*this._getRingWeightOffset(this.index),this.innerRadius=Math.max(this.outerRadius-v*l,0),this.updateElements(n,0,n.length,t)}_circumference(t,e){const i=this.options,s=this._cachedMeta,n=this._getCircumference();return e&&i.animation.animateRotate||!this.chart.getDataVisibility(t)||null===s._parsed[t]||s.data[t].hidden?0:this.calculateCircumference(s._parsed[t]*n/O)}updateElements(t,e,i,s){const n="reset"===s,o=this.chart,a=o.chartArea,r=o.options.animation,l=(a.left+a.right)/2,h=(a.top+a.bottom)/2,c=n&&r.animateScale,d=c?0:this.innerRadius,u=c?0:this.outerRadius,{sharedOptions:f,includeOptions:g}=this._getSharedOptions(e,s);let p,m=this._getRotation();for(p=0;p<e;++p)m+=this._circumference(p,n);for(p=e;p<e+i;++p){const e=this._circumference(p,n),i=t[p],o={x:l+this.offsetX,y:h+this.offsetY,startAngle:m,endAngle:m+e,circumference:e,outerRadius:u,innerRadius:d};g&&(o.options=f||this.resolveDataElementOptions(p,i.active?"active":s)),m+=e,this.updateElement(i,p,o,s)}}calculateTotal(){const t=this._cachedMeta,e=t.data;let i,s=0;for(i=0;i<e.length;i++){const n=t._parsed[i];null===n||isNaN(n)||!this.chart.getDataVisibility(i)||e[i].hidden||(s+=Math.abs(n))}return s}calculateCircumference(t){const e=this._cachedMeta.total;return e>0&&!isNaN(t)?O*(Math.abs(t)/e):0}getLabelAndValue(t){const e=this._cachedMeta,i=this.chart,s=i.data.labels||[],n=ne(e._parsed[t],i.options.locale);return{label:s[t]||"",value:n}}getMaxBorderWidth(t){let e=0;const i=this.chart;let s,n,o,a,r;if(!t)for(s=0,n=i.data.datasets.length;s<n;++s)if(i.isDatasetVisible(s)){o=i.getDatasetMeta(s),t=o.data,a=o.controller;break}if(!t)return 0;for(s=0,n=t.length;s<n;++s)r=a.resolveDataElementOptions(s),"inner"!==r.borderAlign&&(e=Math.max(e,r.borderWidth||0,r.hoverBorderWidth||0));return e}getMaxOffset(t){let e=0;for(let i=0,s=t.length;i<s;++i){const t=this.resolveDataElementOptions(i);e=Math.max(e,t.offset||0,t.hoverOffset||0)}return e}_getRingWeightOffset(t){let e=0;for(let i=0;i<t;++i)this.chart.isDatasetVisible(i)&&(e+=this._getRingWeight(i));return e}_getRingWeight(t){return Math.max(l(this.chart.data.datasets[t].weight,1),0)}_getVisibleDatasetWeightTotal(){return this._getRingWeightOffset(this.chart.data.datasets.length)||1}}class Yn extends js{static id="polarArea";static defaults={dataElementType:"arc",animation:{animateRotate:!0,animateScale:!0},animations:{numbers:{type:"number",properties:["x","y","startAngle","endAngle","innerRadius","outerRadius"]}},indexAxis:"r",startAngle:0};static overrides={aspectRatio:1,plugins:{legend:{labels:{generateLabels(t){const e=t.data;if(e.labels.length&&e.datasets.length){const{labels:{pointStyle:i,color:s}}=t.legend.options;return e.labels.map(((e,n)=>{const o=t.getDatasetMeta(0).controller.getStyle(n);return{text:e,fillStyle:o.backgroundColor,strokeStyle:o.borderColor,fontColor:s,lineWidth:o.borderWidth,pointStyle:i,hidden:!t.getDataVisibility(n),index:n}}))}return[]}},onClick(t,e,i){i.chart.toggleDataVisibility(e.index),i.chart.update()}}},scales:{r:{type:"radialLinear",angleLines:{display:!1},beginAtZero:!0,grid:{circular:!0},pointLabels:{display:!1},startAngle:0}}};constructor(t,e){super(t,e),this.innerRadius=void 0,this.outerRadius=void 0}getLabelAndValue(t){const e=this._cachedMeta,i=this.chart,s=i.data.labels||[],n=ne(e._parsed[t].r,i.options.locale);return{label:s[t]||"",value:n}}parseObjectData(t,e,i,s){return ii.bind(this)(t,e,i,s)}update(t){const e=this._cachedMeta.data;this._updateRadius(),this.updateElements(e,0,e.length,t)}getMinMax(){const t=this._cachedMeta,e={min:Number.POSITIVE_INFINITY,max:Number.NEGATIVE_INFINITY};return t.data.forEach(((t,i)=>{const s=this.getParsed(i).r;!isNaN(s)&&this.chart.getDataVisibility(i)&&(s<e.min&&(e.min=s),s>e.max&&(e.max=s))})),e}_updateRadius(){const t=this.chart,e=t.chartArea,i=t.options,s=Math.min(e.right-e.left,e.bottom-e.top),n=Math.max(s/2,0),o=(n-Math.max(i.cutoutPercentage?n/100*i.cutoutPercentage:1,0))/t.getVisibleDatasetCount();this.outerRadius=n-o*this.index,this.innerRadius=this.outerRadius-o}updateElements(t,e,i,s){const n="reset"===s,o=this.chart,a=o.options.animation,r=this._cachedMeta.rScale,l=r.xCenter,h=r.yCenter,c=r.getIndexAngle(0)-.5*C;let d,u=c;const f=360/this.countVisibleElements();for(d=0;d<e;++d)u+=this._computeAngle(d,s,f);for(d=e;d<e+i;d++){const e=t[d];let i=u,g=u+this._computeAngle(d,s,f),p=o.getDataVisibility(d)?r.getDistanceFromCenterForValue(this.getParsed(d).r):0;u=g,n&&(a.animateScale&&(p=0),a.animateRotate&&(i=g=c));const m={x:l,y:h,innerRadius:0,outerRadius:p,startAngle:i,endAngle:g,options:this.resolveDataElementOptions(d,e.active?"active":s)};this.updateElement(e,d,m,s)}}countVisibleElements(){const t=this._cachedMeta;let e=0;return t.data.forEach(((t,i)=>{!isNaN(this.getParsed(i).r)&&this.chart.getDataVisibility(i)&&e++})),e}_computeAngle(t,e,i){return this.chart.getDataVisibility(t)?$(this.resolveDataElementOptions(t,e).angle||i):0}}var Un=Object.freeze({__proto__:null,BarController:class extends js{static id="bar";static defaults={datasetElementType:!1,dataElementType:"bar",categoryPercentage:.8,barPercentage:.9,grouped:!0,animations:{numbers:{type:"number",properties:["x","y","base","width","height"]}}};static overrides={scales:{_index_:{type:"category",offset:!0,grid:{offset:!0}},_value_:{type:"linear",beginAtZero:!0}}};parsePrimitiveData(t,e,i,s){return Vn(t,e,i,s)}parseArrayData(t,e,i,s){return Vn(t,e,i,s)}parseObjectData(t,e,i,s){const{iScale:n,vScale:o}=t,{xAxisKey:a="x",yAxisKey:r="y"}=this._parsing,l="x"===n.axis?a:r,h="x"===o.axis?a:r,c=[];let d,u,f,g;for(d=i,u=i+s;d<u;++d)g=e[d],f={},f[n.axis]=n.parse(M(g,l),d),c.push(Fn(M(g,h),f,o,d));return c}updateRangeFromParsed(t,e,i,s){super.updateRangeFromParsed(t,e,i,s);const n=i._custom;n&&e===this._cachedMeta.vScale&&(t.min=Math.min(t.min,n.min),t.max=Math.max(t.max,n.max))}getMaxOverflow(){return 0}getLabelAndValue(t){const e=this._cachedMeta,{iScale:i,vScale:s}=e,n=this.getParsed(t),o=n._custom,a=Bn(o)?"["+o.start+", "+o.end+"]":""+s.getLabelForValue(n[s.axis]);return{label:""+i.getLabelForValue(n[i.axis]),value:a}}initialize(){this.enableOptionSharing=!0,super.initialize();this._cachedMeta.stack=this.getDataset().stack}update(t){const e=this._cachedMeta;this.updateElements(e.data,0,e.data.length,t)}updateElements(t,e,i,n){const o="reset"===n,{index:a,_cachedMeta:{vScale:r}}=this,l=r.getBasePixel(),h=r.isHorizontal(),c=this._getRuler(),{sharedOptions:d,includeOptions:u}=this._getSharedOptions(e,n);for(let f=e;f<e+i;f++){const e=this.getParsed(f),i=o||s(e[r.axis])?{base:l,head:l}:this._calculateBarValuePixels(f),g=this._calculateBarIndexPixels(f,c),p=(e._stacks||{})[r.axis],m={horizontal:h,base:i.base,enableBorderRadius:!p||Bn(e._custom)||a===p._top||a===p._bottom,x:h?i.head:g.center,y:h?g.center:i.head,height:h?g.size:Math.abs(i.size),width:h?Math.abs(i.size):g.size};u&&(m.options=d||this.resolveDataElementOptions(f,t[f].active?"active":n));const x=m.options||t[f].options;Wn(m,x,p,a),jn(m,x,c.ratio),this.updateElement(t[f],f,m,n)}}_getStacks(t,e){const{iScale:i}=this._cachedMeta,n=i.getMatchingVisibleMetas(this._type).filter((t=>t.controller.options.grouped)),o=i.options.stacked,a=[],r=this._cachedMeta.controller.getParsed(e),l=r&&r[i.axis],h=t=>{const e=t._parsed.find((t=>t[i.axis]===l)),n=e&&e[t.vScale.axis];if(s(n)||isNaN(n))return!0};for(const i of n)if((void 0===e||!h(i))&&((!1===o||-1===a.indexOf(i.stack)||void 0===o&&void 0===i.stack)&&a.push(i.stack),i.index===t))break;return a.length||a.push(void 0),a}_getStackCount(t){return this._getStacks(void 0,t).length}_getAxisCount(){return this._getAxis().length}getFirstScaleIdForIndexAxis(){const t=this.chart.scales,e=this.chart.options.indexAxis;return Object.keys(t).filter((i=>t[i].axis===e)).shift()}_getAxis(){const t={},e=this.getFirstScaleIdForIndexAxis();for(const i of this.chart.data.datasets)t[l("x"===this.chart.options.indexAxis?i.xAxisID:i.yAxisID,e)]=!0;return Object.keys(t)}_getStackIndex(t,e,i){const s=this._getStacks(t,i),n=void 0!==e?s.indexOf(e):-1;return-1===n?s.length-1:n}_getRuler(){const t=this.options,e=this._cachedMeta,i=e.iScale,s=[];let n,o;for(n=0,o=e.data.length;n<o;++n)s.push(i.getPixelForValue(this.getParsed(n)[i.axis],n));const a=t.barThickness;return{min:a||zn(e),pixels:s,start:i._startPixel,end:i._endPixel,stackCount:this._getStackCount(),scale:i,grouped:t.grouped,ratio:a?1:t.categoryPercentage*t.barPercentage}}_calculateBarValuePixels(t){const{_cachedMeta:{vScale:e,_stacked:i,index:n},options:{base:o,minBarLength:a}}=this,r=o||0,l=this.getParsed(t),h=l._custom,c=Bn(h);let d,u,f=l[e.axis],g=0,p=i?this.applyStack(e,l,i):f;p!==f&&(g=p-f,p=f),c&&(f=h.barStart,p=h.barEnd-h.barStart,0!==f&&F(f)!==F(h.barEnd)&&(g=0),g+=f);const m=s(o)||c?g:o;let x=e.getPixelForValue(m);if(d=this.chart.getDataVisibility(t)?e.getPixelForValue(g+p):x,u=d-x,Math.abs(u)<a){u=function(t,e,i){return 0!==t?F(t):(e.isHorizontal()?1:-1)*(e.min>=i?1:-1)}(u,e,r)*a,f===r&&(x-=u/2);const t=e.getPixelForDecimal(0),s=e.getPixelForDecimal(1),o=Math.min(t,s),h=Math.max(t,s);x=Math.max(Math.min(x,h),o),d=x+u,i&&!c&&(l._stacks[e.axis]._visualValues[n]=e.getValueForPixel(d)-e.getValueForPixel(x))}if(x===e.getPixelForValue(r)){const t=F(u)*e.getLineWidthForValue(r)/2;x+=t,u-=t}return{size:u,base:x,head:d,center:d+u/2}}_calculateBarIndexPixels(t,e){const i=e.scale,n=this.options,o=n.skipNull,a=l(n.maxBarThickness,1/0);let r,h;const c=this._getAxisCount();if(e.grouped){const i=o?this._getStackCount(t):e.stackCount,d="flex"===n.barThickness?function(t,e,i,s){const n=e.pixels,o=n[t];let a=t>0?n[t-1]:null,r=t<n.length-1?n[t+1]:null;const l=i.categoryPercentage;null===a&&(a=o-(null===r?e.end-e.start:r-o)),null===r&&(r=o+o-a);const h=o-(o-Math.min(a,r))/2*l;return{chunk:Math.abs(r-a)/2*l/s,ratio:i.barPercentage,start:h}}(t,e,n,i*c):function(t,e,i,n){const o=i.barThickness;let a,r;return s(o)?(a=e.min*i.categoryPercentage,r=i.barPercentage):(a=o*n,r=1),{chunk:a/n,ratio:r,start:e.pixels[t]-a/2}}(t,e,n,i*c),u="x"===this.chart.options.indexAxis?this.getDataset().xAxisID:this.getDataset().yAxisID,f=this._getAxis().indexOf(l(u,this.getFirstScaleIdForIndexAxis())),g=this._getStackIndex(this.index,this._cachedMeta.stack,o?t:void 0)+f;r=d.start+d.chunk*g+d.chunk/2,h=Math.min(a,d.chunk*d.ratio)}else r=i.getPixelForValue(this.getParsed(t)[i.axis],t),h=Math.min(a,e.min*e.ratio);return{base:r-h/2,head:r+h/2,center:r,size:h}}draw(){const t=this._cachedMeta,e=t.vScale,i=t.data,s=i.length;let n=0;for(;n<s;++n)null===this.getParsed(n)[e.axis]||i[n].hidden||i[n].draw(this._ctx)}},BubbleController:class extends js{static id="bubble";static defaults={datasetElementType:!1,dataElementType:"point",animations:{numbers:{type:"number",properties:["x","y","borderWidth","radius"]}}};static overrides={scales:{x:{type:"linear"},y:{type:"linear"}}};initialize(){this.enableOptionSharing=!0,super.initialize()}parsePrimitiveData(t,e,i,s){const n=super.parsePrimitiveData(t,e,i,s);for(let t=0;t<n.length;t++)n[t]._custom=this.resolveDataElementOptions(t+i).radius;return n}parseArrayData(t,e,i,s){const n=super.parseArrayData(t,e,i,s);for(let t=0;t<n.length;t++){const s=e[i+t];n[t]._custom=l(s[2],this.resolveDataElementOptions(t+i).radius)}return n}parseObjectData(t,e,i,s){const n=super.parseObjectData(t,e,i,s);for(let t=0;t<n.length;t++){const s=e[i+t];n[t]._custom=l(s&&s.r&&+s.r,this.resolveDataElementOptions(t+i).radius)}return n}getMaxOverflow(){const t=this._cachedMeta.data;let e=0;for(let i=t.length-1;i>=0;--i)e=Math.max(e,t[i].size(this.resolveDataElementOptions(i))/2);return e>0&&e}getLabelAndValue(t){const e=this._cachedMeta,i=this.chart.data.labels||[],{xScale:s,yScale:n}=e,o=this.getParsed(t),a=s.getLabelForValue(o.x),r=n.getLabelForValue(o.y),l=o._custom;return{label:i[t]||"",value:"("+a+", "+r+(l?", "+l:"")+")"}}update(t){const e=this._cachedMeta.data;this.updateElements(e,0,e.length,t)}updateElements(t,e,i,s){const n="reset"===s,{iScale:o,vScale:a}=this._cachedMeta,{sharedOptions:r,includeOptions:l}=this._getSharedOptions(e,s),h=o.axis,c=a.axis;for(let d=e;d<e+i;d++){const e=t[d],i=!n&&this.getParsed(d),u={},f=u[h]=n?o.getPixelForDecimal(.5):o.getPixelForValue(i[h]),g=u[c]=n?a.getBasePixel():a.getPixelForValue(i[c]);u.skip=isNaN(f)||isNaN(g),l&&(u.options=r||this.resolveDataElementOptions(d,e.active?"active":s),n&&(u.options.radius=0)),this.updateElement(e,d,u,s)}}resolveDataElementOptions(t,e){const i=this.getParsed(t);let s=super.resolveDataElementOptions(t,e);s.$shared&&(s=Object.assign({},s,{$shared:!1}));const n=s.radius;return"active"!==e&&(s.radius=0),s.radius+=l(i&&i._custom,n),s}},DoughnutController:$n,LineController:class extends js{static id="line";static defaults={datasetElementType:"line",dataElementType:"point",showLine:!0,spanGaps:!1};static overrides={scales:{_index_:{type:"category"},_value_:{type:"linear"}}};initialize(){this.enableOptionSharing=!0,this.supportsDecimation=!0,super.initialize()}update(t){const e=this._cachedMeta,{dataset:i,data:s=[],_dataset:n}=e,o=this.chart._animationsDisabled;let{start:a,count:r}=pt(e,s,o);this._drawStart=a,this._drawCount=r,mt(e)&&(a=0,r=s.length),i._chart=this.chart,i._datasetIndex=this.index,i._decimated=!!n._decimated,i.points=s;const l=this.resolveDatasetElementOptions(t);this.options.showLine||(l.borderWidth=0),l.segment=this.options.segment,this.updateElement(i,void 0,{animated:!o,options:l},t),this.updateElements(s,a,r,t)}updateElements(t,e,i,n){const o="reset"===n,{iScale:a,vScale:r,_stacked:l,_dataset:h}=this._cachedMeta,{sharedOptions:c,includeOptions:d}=this._getSharedOptions(e,n),u=a.axis,f=r.axis,{spanGaps:g,segment:p}=this.options,m=N(g)?g:Number.POSITIVE_INFINITY,x=this.chart._animationsDisabled||o||"none"===n,b=e+i,_=t.length;let y=e>0&&this.getParsed(e-1);for(let i=0;i<_;++i){const g=t[i],_=x?g:{};if(i<e||i>=b){_.skip=!0;continue}const v=this.getParsed(i),M=s(v[f]),w=_[u]=a.getPixelForValue(v[u],i),k=_[f]=o||M?r.getBasePixel():r.getPixelForValue(l?this.applyStack(r,v,l):v[f],i);_.skip=isNaN(w)||isNaN(k)||M,_.stop=i>0&&Math.abs(v[u]-y[u])>m,p&&(_.parsed=v,_.raw=h.data[i]),d&&(_.options=c||this.resolveDataElementOptions(i,g.active?"active":n)),x||this.updateElement(g,i,_,n),y=v}}getMaxOverflow(){const t=this._cachedMeta,e=t.dataset,i=e.options&&e.options.borderWidth||0,s=t.data||[];if(!s.length)return i;const n=s[0].size(this.resolveDataElementOptions(0)),o=s[s.length-1].size(this.resolveDataElementOptions(s.length-1));return Math.max(i,n,o)/2}draw(){const t=this._cachedMeta;t.dataset.updateControlPoints(this.chart.chartArea,t.iScale.axis),super.draw()}},PieController:class extends $n{static id="pie";static defaults={cutout:0,rotation:0,circumference:360,radius:"100%"}},PolarAreaController:Yn,RadarController:class extends js{static id="radar";static defaults={datasetElementType:"line",dataElementType:"point",indexAxis:"r",showLine:!0,elements:{line:{fill:"start"}}};static overrides={aspectRatio:1,scales:{r:{type:"radialLinear"}}};getLabelAndValue(t){const e=this._cachedMeta.vScale,i=this.getParsed(t);return{label:e.getLabels()[t],value:""+e.getLabelForValue(i[e.axis])}}parseObjectData(t,e,i,s){return ii.bind(this)(t,e,i,s)}update(t){const e=this._cachedMeta,i=e.dataset,s=e.data||[],n=e.iScale.getLabels();if(i.points=s,"resize"!==t){const e=this.resolveDatasetElementOptions(t);this.options.showLine||(e.borderWidth=0);const o={_loop:!0,_fullLoop:n.length===s.length,options:e};this.updateElement(i,void 0,o,t)}this.updateElements(s,0,s.length,t)}updateElements(t,e,i,s){const n=this._cachedMeta.rScale,o="reset"===s;for(let a=e;a<e+i;a++){const e=t[a],i=this.resolveDataElementOptions(a,e.active?"active":s),r=n.getPointPositionForValue(a,this.getParsed(a).r),l=o?n.xCenter:r.x,h=o?n.yCenter:r.y,c={x:l,y:h,angle:r.angle,skip:isNaN(l)||isNaN(h),options:i};this.updateElement(e,a,c,s)}}},ScatterController:class extends js{static id="scatter";static defaults={datasetElementType:!1,dataElementType:"point",showLine:!1,fill:!1};static overrides={interaction:{mode:"point"},scales:{x:{type:"linear"},y:{type:"linear"}}};getLabelAndValue(t){const e=this._cachedMeta,i=this.chart.data.labels||[],{xScale:s,yScale:n}=e,o=this.getParsed(t),a=s.getLabelForValue(o.x),r=n.getLabelForValue(o.y);return{label:i[t]||"",value:"("+a+", "+r+")"}}update(t){const e=this._cachedMeta,{data:i=[]}=e,s=this.chart._animationsDisabled;let{start:n,count:o}=pt(e,i,s);if(this._drawStart=n,this._drawCount=o,mt(e)&&(n=0,o=i.length),this.options.showLine){this.datasetElementType||this.addElements();const{dataset:n,_dataset:o}=e;n._chart=this.chart,n._datasetIndex=this.index,n._decimated=!!o._decimated,n.points=i;const a=this.resolveDatasetElementOptions(t);a.segment=this.options.segment,this.updateElement(n,void 0,{animated:!s,options:a},t)}else this.datasetElementType&&(delete e.dataset,this.datasetElementType=!1);this.updateElements(i,n,o,t)}addElements(){const{showLine:t}=this.options;!this.datasetElementType&&t&&(this.datasetElementType=this.chart.registry.getElement("line")),super.addElements()}updateElements(t,e,i,n){const o="reset"===n,{iScale:a,vScale:r,_stacked:l,_dataset:h}=this._cachedMeta,c=this.resolveDataElementOptions(e,n),d=this.getSharedOptions(c),u=this.includeOptions(n,d),f=a.axis,g=r.axis,{spanGaps:p,segment:m}=this.options,x=N(p)?p:Number.POSITIVE_INFINITY,b=this.chart._animationsDisabled||o||"none"===n;let _=e>0&&this.getParsed(e-1);for(let c=e;c<e+i;++c){const e=t[c],i=this.getParsed(c),p=b?e:{},y=s(i[g]),v=p[f]=a.getPixelForValue(i[f],c),M=p[g]=o||y?r.getBasePixel():r.getPixelForValue(l?this.applyStack(r,i,l):i[g],c);p.skip=isNaN(v)||isNaN(M)||y,p.stop=c>0&&Math.abs(i[f]-_[f])>x,m&&(p.parsed=i,p.raw=h.data[c]),u&&(p.options=d||this.resolveDataElementOptions(c,e.active?"active":n)),b||this.updateElement(e,c,p,n),_=i}this.updateSharedOptions(d,n,c)}getMaxOverflow(){const t=this._cachedMeta,e=t.data||[];if(!this.options.showLine){let t=0;for(let i=e.length-1;i>=0;--i)t=Math.max(t,e[i].size(this.resolveDataElementOptions(i))/2);return t>0&&t}const i=t.dataset,s=i.options&&i.options.borderWidth||0;if(!e.length)return s;const n=e[0].size(this.resolveDataElementOptions(0)),o=e[e.length-1].size(this.resolveDataElementOptions(e.length-1));return Math.max(s,n,o)/2}}});function Xn(t,e,i,s){const n=vi(t.options.borderRadius,["outerStart","outerEnd","innerStart","innerEnd"]);const o=(i-e)/2,a=Math.min(o,s*e/2),r=t=>{const e=(i-Math.min(o,t))*s/2;return Z(t,0,Math.min(o,e))};return{outerStart:r(n.outerStart),outerEnd:r(n.outerEnd),innerStart:Z(n.innerStart,0,a),innerEnd:Z(n.innerEnd,0,a)}}function qn(t,e,i,s){return{x:i+t*Math.cos(e),y:s+t*Math.sin(e)}}function Kn(t,e,i,s,n,o){const{x:a,y:r,startAngle:l,pixelMargin:h,innerRadius:c}=e,d=Math.max(e.outerRadius+s+i-h,0),u=c>0?c+s+i+h:0;let f=0;const g=n-l;if(s){const t=((c>0?c-s:0)+(d>0?d-s:0))/2;f=(g-(0!==t?g*t/(t+s):g))/2}const p=(g-Math.max(.001,g*d-i/C)/d)/2,m=l+p+f,x=n-p-f,{outerStart:b,outerEnd:_,innerStart:y,innerEnd:v}=Xn(e,u,d,x-m),M=d-b,w=d-_,k=m+b/M,S=x-_/w,P=u+y,D=u+v,O=m+y/P,A=x-v/D;if(t.beginPath(),o){const e=(k+S)/2;if(t.arc(a,r,d,k,e),t.arc(a,r,d,e,S),_>0){const e=qn(w,S,a,r);t.arc(e.x,e.y,_,S,x+E)}const i=qn(D,x,a,r);if(t.lineTo(i.x,i.y),v>0){const e=qn(D,A,a,r);t.arc(e.x,e.y,v,x+E,A+Math.PI)}const s=(x-v/u+(m+y/u))/2;if(t.arc(a,r,u,x-v/u,s,!0),t.arc(a,r,u,s,m+y/u,!0),y>0){const e=qn(P,O,a,r);t.arc(e.x,e.y,y,O+Math.PI,m-E)}const n=qn(M,m,a,r);if(t.lineTo(n.x,n.y),b>0){const e=qn(M,k,a,r);t.arc(e.x,e.y,b,m-E,k)}}else{t.moveTo(a,r);const e=Math.cos(k)*d+a,i=Math.sin(k)*d+r;t.lineTo(e,i);const s=Math.cos(S)*d+a,n=Math.sin(S)*d+r;t.lineTo(s,n)}t.closePath()}function Gn(t,e,i,s,n){const{fullCircles:o,startAngle:a,circumference:r,options:l}=e,{borderWidth:h,borderJoinStyle:c,borderDash:d,borderDashOffset:u,borderRadius:f}=l,g="inner"===l.borderAlign;if(!h)return;t.setLineDash(d||[]),t.lineDashOffset=u,g?(t.lineWidth=2*h,t.lineJoin=c||"round"):(t.lineWidth=h,t.lineJoin=c||"bevel");let p=e.endAngle;if(o){Kn(t,e,i,s,p,n);for(let e=0;e<o;++e)t.stroke();isNaN(r)||(p=a+(r%O||O))}g&&function(t,e,i){const{startAngle:s,pixelMargin:n,x:o,y:a,outerRadius:r,innerRadius:l}=e;let h=n/r;t.beginPath(),t.arc(o,a,r,s-h,i+h),l>n?(h=n/l,t.arc(o,a,l,i+h,s-h,!0)):t.arc(o,a,n,i+E,s-E),t.closePath(),t.clip()}(t,e,p),l.selfJoin&&p-a>=C&&0===f&&"miter"!==c&&function(t,e,i){const{startAngle:s,x:n,y:o,outerRadius:a,innerRadius:r,options:l}=e,{borderWidth:h,borderJoinStyle:c}=l,d=Math.min(h/a,G(s-i));if(t.beginPath(),t.arc(n,o,a-h/2,s+d/2,i-d/2),r>0){const e=Math.min(h/r,G(s-i));t.arc(n,o,r+h/2,i-e/2,s+e/2,!0)}else{const e=Math.min(h/2,a*G(s-i));if("round"===c)t.arc(n,o,e,i-C/2,s+C/2,!0);else if("bevel"===c){const a=2*e*e,r=-a*Math.cos(i+C/2)+n,l=-a*Math.sin(i+C/2)+o,h=a*Math.cos(s+C/2)+n,c=a*Math.sin(s+C/2)+o;t.lineTo(r,l),t.lineTo(h,c)}}t.closePath(),t.moveTo(0,0),t.rect(0,0,t.canvas.width,t.canvas.height),t.clip("evenodd")}(t,e,p),o||(Kn(t,e,i,s,p,n),t.stroke())}function Jn(t,e,i=e){t.lineCap=l(i.borderCapStyle,e.borderCapStyle),t.setLineDash(l(i.borderDash,e.borderDash)),t.lineDashOffset=l(i.borderDashOffset,e.borderDashOffset),t.lineJoin=l(i.borderJoinStyle,e.borderJoinStyle),t.lineWidth=l(i.borderWidth,e.borderWidth),t.strokeStyle=l(i.borderColor,e.borderColor)}function Zn(t,e,i){t.lineTo(i.x,i.y)}function Qn(t,e,i={}){const s=t.length,{start:n=0,end:o=s-1}=i,{start:a,end:r}=e,l=Math.max(n,a),h=Math.min(o,r),c=n<a&&o<a||n>r&&o>r;return{count:s,start:l,loop:e.loop,ilen:h<l&&!c?s+h-l:h-l}}function to(t,e,i,s){const{points:n,options:o}=e,{count:a,start:r,loop:l,ilen:h}=Qn(n,i,s),c=function(t){return t.stepped?Fe:t.tension||"monotone"===t.cubicInterpolationMode?Ve:Zn}(o);let d,u,f,{move:g=!0,reverse:p}=s||{};for(d=0;d<=h;++d)u=n[(r+(p?h-d:d))%a],u.skip||(g?(t.moveTo(u.x,u.y),g=!1):c(t,f,u,p,o.stepped),f=u);return l&&(u=n[(r+(p?h:0))%a],c(t,f,u,p,o.stepped)),!!l}function eo(t,e,i,s){const n=e.points,{count:o,start:a,ilen:r}=Qn(n,i,s),{move:l=!0,reverse:h}=s||{};let c,d,u,f,g,p,m=0,x=0;const b=t=>(a+(h?r-t:t))%o,_=()=>{f!==g&&(t.lineTo(m,g),t.lineTo(m,f),t.lineTo(m,p))};for(l&&(d=n[b(0)],t.moveTo(d.x,d.y)),c=0;c<=r;++c){if(d=n[b(c)],d.skip)continue;const e=d.x,i=d.y,s=0|e;s===u?(i<f?f=i:i>g&&(g=i),m=(x*m+e)/++x):(_(),t.lineTo(e,i),u=s,x=0,f=g=i),p=i}_()}function io(t){const e=t.options,i=e.borderDash&&e.borderDash.length;return!(t._decimated||t._loop||e.tension||"monotone"===e.cubicInterpolationMode||e.stepped||i)?eo:to}const so="function"==typeof Path2D;function no(t,e,i,s){so&&!e.options.segment?function(t,e,i,s){let n=e._path;n||(n=e._path=new Path2D,e.path(n,i,s)&&n.closePath()),Jn(t,e.options),t.stroke(n)}(t,e,i,s):function(t,e,i,s){const{segments:n,options:o}=e,a=io(e);for(const r of n)Jn(t,o,r.style),t.beginPath(),a(t,e,r,{start:i,end:i+s-1})&&t.closePath(),t.stroke()}(t,e,i,s)}class oo extends $s{static id="line";static defaults={borderCapStyle:"butt",borderDash:[],borderDashOffset:0,borderJoinStyle:"miter",borderWidth:3,capBezierPoints:!0,cubicInterpolationMode:"default",fill:!1,spanGaps:!1,stepped:!1,tension:0};static defaultRoutes={backgroundColor:"backgroundColor",borderColor:"borderColor"};static descriptors={_scriptable:!0,_indexable:t=>"borderDash"!==t&&"fill"!==t};constructor(t){super(),this.animated=!0,this.options=void 0,this._chart=void 0,this._loop=void 0,this._fullLoop=void 0,this._path=void 0,this._points=void 0,this._segments=void 0,this._decimated=!1,this._pointsUpdated=!1,this._datasetIndex=void 0,t&&Object.assign(this,t)}updateControlPoints(t,e){const i=this.options;if((i.tension||"monotone"===i.cubicInterpolationMode)&&!i.stepped&&!this._pointsUpdated){const s=i.spanGaps?this._loop:this._fullLoop;hi(this._points,i,t,s,e),this._pointsUpdated=!0}}set points(t){this._points=t,delete this._segments,delete this._path,this._pointsUpdated=!1}get points(){return this._points}get segments(){return this._segments||(this._segments=zi(this,this.options.segment))}first(){const t=this.segments,e=this.points;return t.length&&e[t[0].start]}last(){const t=this.segments,e=this.points,i=t.length;return i&&e[t[i-1].end]}interpolate(t,e){const i=this.options,s=t[e],n=this.points,o=Ii(this,{property:e,start:s,end:s});if(!o.length)return;const a=[],r=function(t){return t.stepped?pi:t.tension||"monotone"===t.cubicInterpolationMode?mi:gi}(i);let l,h;for(l=0,h=o.length;l<h;++l){const{start:h,end:c}=o[l],d=n[h],u=n[c];if(d===u){a.push(d);continue}const f=r(d,u,Math.abs((s-d[e])/(u[e]-d[e])),i.stepped);f[e]=t[e],a.push(f)}return 1===a.length?a[0]:a}pathSegment(t,e,i){return io(this)(t,this,e,i)}path(t,e,i){const s=this.segments,n=io(this);let o=this._loop;e=e||0,i=i||this.points.length-e;for(const a of s)o&=n(t,this,a,{start:e,end:e+i-1});return!!o}draw(t,e,i,s){const n=this.options||{};(this.points||[]).length&&n.borderWidth&&(t.save(),no(t,this,i,s),t.restore()),this.animated&&(this._pointsUpdated=!1,this._path=void 0)}}function ao(t,e,i,s){const n=t.options,{[i]:o}=t.getProps([i],s);return Math.abs(e-o)<n.radius+n.hitRadius}function ro(t,e){const{x:i,y:s,base:n,width:o,height:a}=t.getProps(["x","y","base","width","height"],e);let r,l,h,c,d;return t.horizontal?(d=a/2,r=Math.min(i,n),l=Math.max(i,n),h=s-d,c=s+d):(d=o/2,r=i-d,l=i+d,h=Math.min(s,n),c=Math.max(s,n)),{left:r,top:h,right:l,bottom:c}}function lo(t,e,i,s){return t?0:Z(e,i,s)}function ho(t){const e=ro(t),i=e.right-e.left,s=e.bottom-e.top,n=function(t,e,i){const s=t.options.borderWidth,n=t.borderSkipped,o=Mi(s);return{t:lo(n.top,o.top,0,i),r:lo(n.right,o.right,0,e),b:lo(n.bottom,o.bottom,0,i),l:lo(n.left,o.left,0,e)}}(t,i/2,s/2),a=function(t,e,i){const{enableBorderRadius:s}=t.getProps(["enableBorderRadius"]),n=t.options.borderRadius,a=wi(n),r=Math.min(e,i),l=t.borderSkipped,h=s||o(n);return{topLeft:lo(!h||l.top||l.left,a.topLeft,0,r),topRight:lo(!h||l.top||l.right,a.topRight,0,r),bottomLeft:lo(!h||l.bottom||l.left,a.bottomLeft,0,r),bottomRight:lo(!h||l.bottom||l.right,a.bottomRight,0,r)}}(t,i/2,s/2);return{outer:{x:e.left,y:e.top,w:i,h:s,radius:a},inner:{x:e.left+n.l,y:e.top+n.t,w:i-n.l-n.r,h:s-n.t-n.b,radius:{topLeft:Math.max(0,a.topLeft-Math.max(n.t,n.l)),topRight:Math.max(0,a.topRight-Math.max(n.t,n.r)),bottomLeft:Math.max(0,a.bottomLeft-Math.max(n.b,n.l)),bottomRight:Math.max(0,a.bottomRight-Math.max(n.b,n.r))}}}}function co(t,e,i,s){const n=null===e,o=null===i,a=t&&!(n&&o)&&ro(t,s);return a&&(n||tt(e,a.left,a.right))&&(o||tt(i,a.top,a.bottom))}function uo(t,e){t.rect(e.x,e.y,e.w,e.h)}function fo(t,e,i={}){const s=t.x!==i.x?-e:0,n=t.y!==i.y?-e:0,o=(t.x+t.w!==i.x+i.w?e:0)-s,a=(t.y+t.h!==i.y+i.h?e:0)-n;return{x:t.x+s,y:t.y+n,w:t.w+o,h:t.h+a,radius:t.radius}}var go=Object.freeze({__proto__:null,ArcElement:class extends $s{static id="arc";static defaults={borderAlign:"center",borderColor:"#fff",borderDash:[],borderDashOffset:0,borderJoinStyle:void 0,borderRadius:0,borderWidth:2,offset:0,spacing:0,angle:void 0,circular:!0,selfJoin:!1};static defaultRoutes={backgroundColor:"backgroundColor"};static descriptors={_scriptable:!0,_indexable:t=>"borderDash"!==t};circumference;endAngle;fullCircles;innerRadius;outerRadius;pixelMargin;startAngle;constructor(t){super(),this.options=void 0,this.circumference=void 0,this.startAngle=void 0,this.endAngle=void 0,this.innerRadius=void 0,this.outerRadius=void 0,this.pixelMargin=0,this.fullCircles=0,t&&Object.assign(this,t)}inRange(t,e,i){const s=this.getProps(["x","y"],i),{angle:n,distance:o}=X(s,{x:t,y:e}),{startAngle:a,endAngle:r,innerRadius:h,outerRadius:c,circumference:d}=this.getProps(["startAngle","endAngle","innerRadius","outerRadius","circumference"],i),u=(this.options.spacing+this.options.borderWidth)/2,f=l(d,r-a),g=J(n,a,r)&&a!==r,p=f>=O||g,m=tt(o,h+u,c+u);return p&&m}getCenterPoint(t){const{x:e,y:i,startAngle:s,endAngle:n,innerRadius:o,outerRadius:a}=this.getProps(["x","y","startAngle","endAngle","innerRadius","outerRadius"],t),{offset:r,spacing:l}=this.options,h=(s+n)/2,c=(o+a+l+r)/2;return{x:e+Math.cos(h)*c,y:i+Math.sin(h)*c}}tooltipPosition(t){return this.getCenterPoint(t)}draw(t){const{options:e,circumference:i}=this,s=(e.offset||0)/4,n=(e.spacing||0)/2,o=e.circular;if(this.pixelMargin="inner"===e.borderAlign?.33:0,this.fullCircles=i>O?Math.floor(i/O):0,0===i||this.innerRadius<0||this.outerRadius<0)return;t.save();const a=(this.startAngle+this.endAngle)/2;t.translate(Math.cos(a)*s,Math.sin(a)*s);const r=s*(1-Math.sin(Math.min(C,i||0)));t.fillStyle=e.backgroundColor,t.strokeStyle=e.borderColor,function(t,e,i,s,n){const{fullCircles:o,startAngle:a,circumference:r}=e;let l=e.endAngle;if(o){Kn(t,e,i,s,l,n);for(let e=0;e<o;++e)t.fill();isNaN(r)||(l=a+(r%O||O))}Kn(t,e,i,s,l,n),t.fill()}(t,this,r,n,o),Gn(t,this,r,n,o),t.restore()}},BarElement:class extends $s{static id="bar";static defaults={borderSkipped:"start",borderWidth:0,borderRadius:0,inflateAmount:"auto",pointStyle:void 0};static defaultRoutes={backgroundColor:"backgroundColor",borderColor:"borderColor"};constructor(t){super(),this.options=void 0,this.horizontal=void 0,this.base=void 0,this.width=void 0,this.height=void 0,this.inflateAmount=void 0,t&&Object.assign(this,t)}draw(t){const{inflateAmount:e,options:{borderColor:i,backgroundColor:s}}=this,{inner:n,outer:o}=ho(this),a=(r=o.radius).topLeft||r.topRight||r.bottomLeft||r.bottomRight?He:uo;var r;t.save(),o.w===n.w&&o.h===n.h||(t.beginPath(),a(t,fo(o,e,n)),t.clip(),a(t,fo(n,-e,o)),t.fillStyle=i,t.fill("evenodd")),t.beginPath(),a(t,fo(n,e)),t.fillStyle=s,t.fill(),t.restore()}inRange(t,e,i){return co(this,t,e,i)}inXRange(t,e){return co(this,t,null,e)}inYRange(t,e){return co(this,null,t,e)}getCenterPoint(t){const{x:e,y:i,base:s,horizontal:n}=this.getProps(["x","y","base","horizontal"],t);return{x:n?(e+s)/2:e,y:n?i:(i+s)/2}}getRange(t){return"x"===t?this.width/2:this.height/2}},LineElement:oo,PointElement:class extends $s{static id="point";parsed;skip;stop;static defaults={borderWidth:1,hitRadius:1,hoverBorderWidth:1,hoverRadius:4,pointStyle:"circle",radius:3,rotation:0};static defaultRoutes={backgroundColor:"backgroundColor",borderColor:"borderColor"};constructor(t){super(),this.options=void 0,this.parsed=void 0,this.skip=void 0,this.stop=void 0,t&&Object.assign(this,t)}inRange(t,e,i){const s=this.options,{x:n,y:o}=this.getProps(["x","y"],i);return Math.pow(t-n,2)+Math.pow(e-o,2)<Math.pow(s.hitRadius+s.radius,2)}inXRange(t,e){return ao(this,t,"x",e)}inYRange(t,e){return ao(this,t,"y",e)}getCenterPoint(t){const{x:e,y:i}=this.getProps(["x","y"],t);return{x:e,y:i}}size(t){let e=(t=t||this.options||{}).radius||0;e=Math.max(e,e&&t.hoverRadius||0);return 2*(e+(e&&t.borderWidth||0))}draw(t,e){const i=this.options;this.skip||i.radius<.1||!Re(this,e,this.size(i)/2)||(t.strokeStyle=i.borderColor,t.lineWidth=i.borderWidth,t.fillStyle=i.backgroundColor,Le(t,i,this.x,this.y))}getRange(){const t=this.options||{};return t.radius+t.hitRadius}}});function po(t,e,i,s){const n=t.indexOf(e);if(-1===n)return((t,e,i,s)=>("string"==typeof e?(i=t.push(e)-1,s.unshift({index:i,label:e})):isNaN(e)&&(i=null),i))(t,e,i,s);return n!==t.lastIndexOf(e)?i:n}function mo(t){const e=this.getLabels();return t>=0&&t<e.length?e[t]:t}function xo(t,e,{horizontal:i,minRotation:s}){const n=$(s),o=(i?Math.sin(n):Math.cos(n))||.001,a=.75*e*(""+t).length;return Math.min(e/o,a)}class bo extends tn{constructor(t){super(t),this.start=void 0,this.end=void 0,this._startValue=void 0,this._endValue=void 0,this._valueRange=0}parse(t,e){return s(t)||("number"==typeof t||t instanceof Number)&&!isFinite(+t)?null:+t}handleTickRangeOptions(){const{beginAtZero:t}=this.options,{minDefined:e,maxDefined:i}=this.getUserBounds();let{min:s,max:n}=this;const o=t=>s=e?s:t,a=t=>n=i?n:t;if(t){const t=F(s),e=F(n);t<0&&e<0?a(0):t>0&&e>0&&o(0)}if(s===n){let e=0===n?1:Math.abs(.05*n);a(n+e),t||o(s-e)}this.min=s,this.max=n}getTickLimit(){const t=this.options.ticks;let e,{maxTicksLimit:i,stepSize:s}=t;return s?(e=Math.ceil(this.max/s)-Math.floor(this.min/s)+1,e>1e3&&(console.warn(`scales.${this.id}.ticks.stepSize: ${s} would result generating up to ${e} ticks. Limiting to 1000.`),e=1e3)):(e=this.computeTickLimit(),i=i||11),i&&(e=Math.min(i,e)),e}computeTickLimit(){return Number.POSITIVE_INFINITY}buildTicks(){const t=this.options,e=t.ticks;let i=this.getTickLimit();i=Math.max(2,i);const n=function(t,e){const i=[],{bounds:n,step:o,min:a,max:r,precision:l,count:h,maxTicks:c,maxDigits:d,includeBounds:u}=t,f=o||1,g=c-1,{min:p,max:m}=e,x=!s(a),b=!s(r),_=!s(h),y=(m-p)/(d+1);let v,M,w,k,S=B((m-p)/g/f)*f;if(S<1e-14&&!x&&!b)return[{value:p},{value:m}];k=Math.ceil(m/S)-Math.floor(p/S),k>g&&(S=B(k*S/g/f)*f),s(l)||(v=Math.pow(10,l),S=Math.ceil(S*v)/v),"ticks"===n?(M=Math.floor(p/S)*S,w=Math.ceil(m/S)*S):(M=p,w=m),x&&b&&o&&H((r-a)/o,S/1e3)?(k=Math.round(Math.min((r-a)/S,c)),S=(r-a)/k,M=a,w=r):_?(M=x?a:M,w=b?r:w,k=h-1,S=(w-M)/k):(k=(w-M)/S,k=V(k,Math.round(k),S/1e3)?Math.round(k):Math.ceil(k));const P=Math.max(U(S),U(M));v=Math.pow(10,s(l)?P:l),M=Math.round(M*v)/v,w=Math.round(w*v)/v;let D=0;for(x&&(u&&M!==a?(i.push({value:a}),M<a&&D++,V(Math.round((M+D*S)*v)/v,a,xo(a,y,t))&&D++):M<a&&D++);D<k;++D){const t=Math.round((M+D*S)*v)/v;if(b&&t>r)break;i.push({value:t})}return b&&u&&w!==r?i.length&&V(i[i.length-1].value,r,xo(r,y,t))?i[i.length-1].value=r:i.push({value:r}):b&&w!==r||i.push({value:w}),i}({maxTicks:i,bounds:t.bounds,min:t.min,max:t.max,precision:e.precision,step:e.stepSize,count:e.count,maxDigits:this._maxDigits(),horizontal:this.isHorizontal(),minRotation:e.minRotation||0,includeBounds:!1!==e.includeBounds},this._range||this);return"ticks"===t.bounds&&j(n,this,"value"),t.reverse?(n.reverse(),this.start=this.max,this.end=this.min):(this.start=this.min,this.end=this.max),n}configure(){const t=this.ticks;let e=this.min,i=this.max;if(super.configure(),this.options.offset&&t.length){const s=(i-e)/Math.max(t.length-1,1)/2;e-=s,i+=s}this._startValue=e,this._endValue=i,this._valueRange=i-e}getLabelForValue(t){return ne(t,this.chart.options.locale,this.options.ticks.format)}}class _o extends bo{static id="linear";static defaults={ticks:{callback:ae.formatters.numeric}};determineDataLimits(){const{min:t,max:e}=this.getMinMax(!0);this.min=a(t)?t:0,this.max=a(e)?e:1,this.handleTickRangeOptions()}computeTickLimit(){const t=this.isHorizontal(),e=t?this.width:this.height,i=$(this.options.ticks.minRotation),s=(t?Math.sin(i):Math.cos(i))||.001,n=this._resolveTickFontOptions(0);return Math.ceil(e/Math.min(40,n.lineHeight/s))}getPixelForValue(t){return null===t?NaN:this.getPixelForDecimal((t-this._startValue)/this._valueRange)}getValueForPixel(t){return this._startValue+this.getDecimalForPixel(t)*this._valueRange}}const yo=t=>Math.floor(z(t)),vo=(t,e)=>Math.pow(10,yo(t)+e);function Mo(t){return 1===t/Math.pow(10,yo(t))}function wo(t,e,i){const s=Math.pow(10,i),n=Math.floor(t/s);return Math.ceil(e/s)-n}function ko(t,{min:e,max:i}){e=r(t.min,e);const s=[],n=yo(e);let o=function(t,e){let i=yo(e-t);for(;wo(t,e,i)>10;)i++;for(;wo(t,e,i)<10;)i--;return Math.min(i,yo(t))}(e,i),a=o<0?Math.pow(10,Math.abs(o)):1;const l=Math.pow(10,o),h=n>o?Math.pow(10,n):0,c=Math.round((e-h)*a)/a,d=Math.floor((e-h)/l/10)*l*10;let u=Math.floor((c-d)/Math.pow(10,o)),f=r(t.min,Math.round((h+d+u*Math.pow(10,o))*a)/a);for(;f<i;)s.push({value:f,major:Mo(f),significand:u}),u>=10?u=u<15?15:20:u++,u>=20&&(o++,u=2,a=o>=0?1:a),f=Math.round((h+d+u*Math.pow(10,o))*a)/a;const g=r(t.max,f);return s.push({value:g,major:Mo(g),significand:u}),s}class So extends tn{static id="logarithmic";static defaults={ticks:{callback:ae.formatters.logarithmic,major:{enabled:!0}}};constructor(t){super(t),this.start=void 0,this.end=void 0,this._startValue=void 0,this._valueRange=0}parse(t,e){const i=bo.prototype.parse.apply(this,[t,e]);if(0!==i)return a(i)&&i>0?i:null;this._zero=!0}determineDataLimits(){const{min:t,max:e}=this.getMinMax(!0);this.min=a(t)?Math.max(0,t):null,this.max=a(e)?Math.max(0,e):null,this.options.beginAtZero&&(this._zero=!0),this._zero&&this.min!==this._suggestedMin&&!a(this._userMin)&&(this.min=t===vo(this.min,0)?vo(this.min,-1):vo(this.min,0)),this.handleTickRangeOptions()}handleTickRangeOptions(){const{minDefined:t,maxDefined:e}=this.getUserBounds();let i=this.min,s=this.max;const n=e=>i=t?i:e,o=t=>s=e?s:t;i===s&&(i<=0?(n(1),o(10)):(n(vo(i,-1)),o(vo(s,1)))),i<=0&&n(vo(s,-1)),s<=0&&o(vo(i,1)),this.min=i,this.max=s}buildTicks(){const t=this.options,e=ko({min:this._userMin,max:this._userMax},this);return"ticks"===t.bounds&&j(e,this,"value"),t.reverse?(e.reverse(),this.start=this.max,this.end=this.min):(this.start=this.min,this.end=this.max),e}getLabelForValue(t){return void 0===t?"0":ne(t,this.chart.options.locale,this.options.ticks.format)}configure(){const t=this.min;super.configure(),this._startValue=z(t),this._valueRange=z(this.max)-z(t)}getPixelForValue(t){return void 0!==t&&0!==t||(t=this.min),null===t||isNaN(t)?NaN:this.getPixelForDecimal(t===this.min?0:(z(t)-this._startValue)/this._valueRange)}getValueForPixel(t){const e=this.getDecimalForPixel(t);return Math.pow(10,this._startValue+e*this._valueRange)}}function Po(t){const e=t.ticks;if(e.display&&t.display){const t=ki(e.backdropPadding);return l(e.font&&e.font.size,ue.font.size)+t.height}return 0}function Do(t,e,i,s,n){return t===s||t===n?{start:e-i/2,end:e+i/2}:t<s||t>n?{start:e-i,end:e}:{start:e,end:e+i}}function Co(t){const e={l:t.left+t._padding.left,r:t.right-t._padding.right,t:t.top+t._padding.top,b:t.bottom-t._padding.bottom},i=Object.assign({},e),s=[],o=[],a=t._pointLabels.length,r=t.options.pointLabels,l=r.centerPointLabels?C/a:0;for(let u=0;u<a;u++){const a=r.setContext(t.getPointLabelContext(u));o[u]=a.padding;const f=t.getPointPosition(u,t.drawingArea+o[u],l),g=Si(a.font),p=(h=t.ctx,c=g,d=n(d=t._pointLabels[u])?d:[d],{w:Oe(h,c.string,d),h:d.length*c.lineHeight});s[u]=p;const m=G(t.getIndexAngle(u)+l),x=Math.round(Y(m));Oo(i,e,m,Do(x,f.x,p.w,0,180),Do(x,f.y,p.h,90,270))}var h,c,d;t.setCenterPoint(e.l-i.l,i.r-e.r,e.t-i.t,i.b-e.b),t._pointLabelItems=function(t,e,i){const s=[],n=t._pointLabels.length,o=t.options,{centerPointLabels:a,display:r}=o.pointLabels,l={extra:Po(o)/2,additionalAngle:a?C/n:0};let h;for(let o=0;o<n;o++){l.padding=i[o],l.size=e[o];const n=Ao(t,o,l);s.push(n),"auto"===r&&(n.visible=To(n,h),n.visible&&(h=n))}return s}(t,s,o)}function Oo(t,e,i,s,n){const o=Math.abs(Math.sin(i)),a=Math.abs(Math.cos(i));let r=0,l=0;s.start<e.l?(r=(e.l-s.start)/o,t.l=Math.min(t.l,e.l-r)):s.end>e.r&&(r=(s.end-e.r)/o,t.r=Math.max(t.r,e.r+r)),n.start<e.t?(l=(e.t-n.start)/a,t.t=Math.min(t.t,e.t-l)):n.end>e.b&&(l=(n.end-e.b)/a,t.b=Math.max(t.b,e.b+l))}function Ao(t,e,i){const s=t.drawingArea,{extra:n,additionalAngle:o,padding:a,size:r}=i,l=t.getPointPosition(e,s+n+a,o),h=Math.round(Y(G(l.angle+E))),c=function(t,e,i){90===i||270===i?t-=e/2:(i>270||i<90)&&(t-=e);return t}(l.y,r.h,h),d=function(t){if(0===t||180===t)return"center";if(t<180)return"left";return"right"}(h),u=function(t,e,i){"right"===i?t-=e:"center"===i&&(t-=e/2);return t}(l.x,r.w,d);return{visible:!0,x:l.x,y:c,textAlign:d,left:u,top:c,right:u+r.w,bottom:c+r.h}}function To(t,e){if(!e)return!0;const{left:i,top:s,right:n,bottom:o}=t;return!(Re({x:i,y:s},e)||Re({x:i,y:o},e)||Re({x:n,y:s},e)||Re({x:n,y:o},e))}function Lo(t,e,i){const{left:n,top:o,right:a,bottom:r}=i,{backdropColor:l}=e;if(!s(l)){const i=wi(e.borderRadius),s=ki(e.backdropPadding);t.fillStyle=l;const h=n-s.left,c=o-s.top,d=a-n+s.width,u=r-o+s.height;Object.values(i).some((t=>0!==t))?(t.beginPath(),He(t,{x:h,y:c,w:d,h:u,radius:i}),t.fill()):t.fillRect(h,c,d,u)}}function Eo(t,e,i,s){const{ctx:n}=t;if(i)n.arc(t.xCenter,t.yCenter,e,0,O);else{let i=t.getPointPosition(0,e);n.moveTo(i.x,i.y);for(let o=1;o<s;o++)i=t.getPointPosition(o,e),n.lineTo(i.x,i.y)}}class Ro extends bo{static id="radialLinear";static defaults={display:!0,animate:!0,position:"chartArea",angleLines:{display:!0,lineWidth:1,borderDash:[],borderDashOffset:0},grid:{circular:!1},startAngle:0,ticks:{showLabelBackdrop:!0,callback:ae.formatters.numeric},pointLabels:{backdropColor:void 0,backdropPadding:2,display:!0,font:{size:10},callback:t=>t,padding:5,centerPointLabels:!1}};static defaultRoutes={"angleLines.color":"borderColor","pointLabels.color":"color","ticks.color":"color"};static descriptors={angleLines:{_fallback:"grid"}};constructor(t){super(t),this.xCenter=void 0,this.yCenter=void 0,this.drawingArea=void 0,this._pointLabels=[],this._pointLabelItems=[]}setDimensions(){const t=this._padding=ki(Po(this.options)/2),e=this.width=this.maxWidth-t.width,i=this.height=this.maxHeight-t.height;this.xCenter=Math.floor(this.left+e/2+t.left),this.yCenter=Math.floor(this.top+i/2+t.top),this.drawingArea=Math.floor(Math.min(e,i)/2)}determineDataLimits(){const{min:t,max:e}=this.getMinMax(!1);this.min=a(t)&&!isNaN(t)?t:0,this.max=a(e)&&!isNaN(e)?e:0,this.handleTickRangeOptions()}computeTickLimit(){return Math.ceil(this.drawingArea/Po(this.options))}generateTickLabels(t){bo.prototype.generateTickLabels.call(this,t),this._pointLabels=this.getLabels().map(((t,e)=>{const i=d(this.options.pointLabels.callback,[t,e],this);return i||0===i?i:""})).filter(((t,e)=>this.chart.getDataVisibility(e)))}fit(){const t=this.options;t.display&&t.pointLabels.display?Co(this):this.setCenterPoint(0,0,0,0)}setCenterPoint(t,e,i,s){this.xCenter+=Math.floor((t-e)/2),this.yCenter+=Math.floor((i-s)/2),this.drawingArea-=Math.min(this.drawingArea/2,Math.max(t,e,i,s))}getIndexAngle(t){return G(t*(O/(this._pointLabels.length||1))+$(this.options.startAngle||0))}getDistanceFromCenterForValue(t){if(s(t))return NaN;const e=this.drawingArea/(this.max-this.min);return this.options.reverse?(this.max-t)*e:(t-this.min)*e}getValueForDistanceFromCenter(t){if(s(t))return NaN;const e=t/(this.drawingArea/(this.max-this.min));return this.options.reverse?this.max-e:this.min+e}getPointLabelContext(t){const e=this._pointLabels||[];if(t>=0&&t<e.length){const i=e[t];return function(t,e,i){return Ci(t,{label:i,index:e,type:"pointLabel"})}(this.getContext(),t,i)}}getPointPosition(t,e,i=0){const s=this.getIndexAngle(t)-E+i;return{x:Math.cos(s)*e+this.xCenter,y:Math.sin(s)*e+this.yCenter,angle:s}}getPointPositionForValue(t,e){return this.getPointPosition(t,this.getDistanceFromCenterForValue(e))}getBasePosition(t){return this.getPointPositionForValue(t||0,this.getBaseValue())}getPointLabelPosition(t){const{left:e,top:i,right:s,bottom:n}=this._pointLabelItems[t];return{left:e,top:i,right:s,bottom:n}}drawBackground(){const{backgroundColor:t,grid:{circular:e}}=this.options;if(t){const i=this.ctx;i.save(),i.beginPath(),Eo(this,this.getDistanceFromCenterForValue(this._endValue),e,this._pointLabels.length),i.closePath(),i.fillStyle=t,i.fill(),i.restore()}}drawGrid(){const t=this.ctx,e=this.options,{angleLines:i,grid:s,border:n}=e,o=this._pointLabels.length;let a,r,l;if(e.pointLabels.display&&function(t,e){const{ctx:i,options:{pointLabels:s}}=t;for(let n=e-1;n>=0;n--){const e=t._pointLabelItems[n];if(!e.visible)continue;const o=s.setContext(t.getPointLabelContext(n));Lo(i,o,e);const a=Si(o.font),{x:r,y:l,textAlign:h}=e;Ne(i,t._pointLabels[n],r,l+a.lineHeight/2,a,{color:o.color,textAlign:h,textBaseline:"middle"})}}(this,o),s.display&&this.ticks.forEach(((t,e)=>{if(0!==e||0===e&&this.min<0){r=this.getDistanceFromCenterForValue(t.value);const i=this.getContext(e),a=s.setContext(i),l=n.setContext(i);!function(t,e,i,s,n){const o=t.ctx,a=e.circular,{color:r,lineWidth:l}=e;!a&&!s||!r||!l||i<0||(o.save(),o.strokeStyle=r,o.lineWidth=l,o.setLineDash(n.dash||[]),o.lineDashOffset=n.dashOffset,o.beginPath(),Eo(t,i,a,s),o.closePath(),o.stroke(),o.restore())}(this,a,r,o,l)}})),i.display){for(t.save(),a=o-1;a>=0;a--){const s=i.setContext(this.getPointLabelContext(a)),{color:n,lineWidth:o}=s;o&&n&&(t.lineWidth=o,t.strokeStyle=n,t.setLineDash(s.borderDash),t.lineDashOffset=s.borderDashOffset,r=this.getDistanceFromCenterForValue(e.reverse?this.min:this.max),l=this.getPointPosition(a,r),t.beginPath(),t.moveTo(this.xCenter,this.yCenter),t.lineTo(l.x,l.y),t.stroke())}t.restore()}}drawBorder(){}drawLabels(){const t=this.ctx,e=this.options,i=e.ticks;if(!i.display)return;const s=this.getIndexAngle(0);let n,o;t.save(),t.translate(this.xCenter,this.yCenter),t.rotate(s),t.textAlign="center",t.textBaseline="middle",this.ticks.forEach(((s,a)=>{if(0===a&&this.min>=0&&!e.reverse)return;const r=i.setContext(this.getContext(a)),l=Si(r.font);if(n=this.getDistanceFromCenterForValue(this.ticks[a].value),r.showLabelBackdrop){t.font=l.string,o=t.measureText(s.label).width,t.fillStyle=r.backdropColor;const e=ki(r.backdropPadding);t.fillRect(-o/2-e.left,-n-l.size/2-e.top,o+e.width,l.size+e.height)}Ne(t,s.label,0,-n,l,{color:r.color,strokeColor:r.textStrokeColor,strokeWidth:r.textStrokeWidth})})),t.restore()}drawTitle(){}}const Io={millisecond:{common:!0,size:1,steps:1e3},second:{common:!0,size:1e3,steps:60},minute:{common:!0,size:6e4,steps:60},hour:{common:!0,size:36e5,steps:24},day:{common:!0,size:864e5,steps:30},week:{common:!1,size:6048e5,steps:4},month:{common:!0,size:2628e6,steps:12},quarter:{common:!1,size:7884e6,steps:4},year:{common:!0,size:3154e7}},zo=Object.keys(Io);function Fo(t,e){return t-e}function Vo(t,e){if(s(e))return null;const i=t._adapter,{parser:n,round:o,isoWeekday:r}=t._parseOpts;let l=e;return"function"==typeof n&&(l=n(l)),a(l)||(l="string"==typeof n?i.parse(l,n):i.parse(l)),null===l?null:(o&&(l="week"!==o||!N(r)&&!0!==r?i.startOf(l,o):i.startOf(l,"isoWeek",r)),+l)}function Bo(t,e,i,s){const n=zo.length;for(let o=zo.indexOf(t);o<n-1;++o){const t=Io[zo[o]],n=t.steps?t.steps:Number.MAX_SAFE_INTEGER;if(t.common&&Math.ceil((i-e)/(n*t.size))<=s)return zo[o]}return zo[n-1]}function Wo(t,e,i){if(i){if(i.length){const{lo:s,hi:n}=et(i,e);t[i[s]>=e?i[s]:i[n]]=!0}}else t[e]=!0}function No(t,e,i){const s=[],n={},o=e.length;let a,r;for(a=0;a<o;++a)r=e[a],n[r]=a,s.push({value:r,major:!1});return 0!==o&&i?function(t,e,i,s){const n=t._adapter,o=+n.startOf(e[0].value,s),a=e[e.length-1].value;let r,l;for(r=o;r<=a;r=+n.add(r,1,s))l=i[r],l>=0&&(e[l].major=!0);return e}(t,s,n,i):s}class Ho extends tn{static id="time";static defaults={bounds:"data",adapters:{},time:{parser:!1,unit:!1,round:!1,isoWeekday:!1,minUnit:"millisecond",displayFormats:{}},ticks:{source:"auto",callback:!1,major:{enabled:!1}}};constructor(t){super(t),this._cache={data:[],labels:[],all:[]},this._unit="day",this._majorUnit=void 0,this._offsets={},this._normalized=!1,this._parseOpts=void 0}init(t,e={}){const i=t.time||(t.time={}),s=this._adapter=new In._date(t.adapters.date);s.init(e),b(i.displayFormats,s.formats()),this._parseOpts={parser:i.parser,round:i.round,isoWeekday:i.isoWeekday},super.init(t),this._normalized=e.normalized}parse(t,e){return void 0===t?null:Vo(this,t)}beforeLayout(){super.beforeLayout(),this._cache={data:[],labels:[],all:[]}}determineDataLimits(){const t=this.options,e=this._adapter,i=t.time.unit||"day";let{min:s,max:n,minDefined:o,maxDefined:r}=this.getUserBounds();function l(t){o||isNaN(t.min)||(s=Math.min(s,t.min)),r||isNaN(t.max)||(n=Math.max(n,t.max))}o&&r||(l(this._getLabelBounds()),"ticks"===t.bounds&&"labels"===t.ticks.source||l(this.getMinMax(!1))),s=a(s)&&!isNaN(s)?s:+e.startOf(Date.now(),i),n=a(n)&&!isNaN(n)?n:+e.endOf(Date.now(),i)+1,this.min=Math.min(s,n-1),this.max=Math.max(s+1,n)}_getLabelBounds(){const t=this.getLabelTimestamps();let e=Number.POSITIVE_INFINITY,i=Number.NEGATIVE_INFINITY;return t.length&&(e=t[0],i=t[t.length-1]),{min:e,max:i}}buildTicks(){const t=this.options,e=t.time,i=t.ticks,s="labels"===i.source?this.getLabelTimestamps():this._generate();"ticks"===t.bounds&&s.length&&(this.min=this._userMin||s[0],this.max=this._userMax||s[s.length-1]);const n=this.min,o=nt(s,n,this.max);return this._unit=e.unit||(i.autoSkip?Bo(e.minUnit,this.min,this.max,this._getLabelCapacity(n)):function(t,e,i,s,n){for(let o=zo.length-1;o>=zo.indexOf(i);o--){const i=zo[o];if(Io[i].common&&t._adapter.diff(n,s,i)>=e-1)return i}return zo[i?zo.indexOf(i):0]}(this,o.length,e.minUnit,this.min,this.max)),this._majorUnit=i.major.enabled&&"year"!==this._unit?function(t){for(let e=zo.indexOf(t)+1,i=zo.length;e<i;++e)if(Io[zo[e]].common)return zo[e]}(this._unit):void 0,this.initOffsets(s),t.reverse&&o.reverse(),No(this,o,this._majorUnit)}afterAutoSkip(){this.options.offsetAfterAutoskip&&this.initOffsets(this.ticks.map((t=>+t.value)))}initOffsets(t=[]){let e,i,s=0,n=0;this.options.offset&&t.length&&(e=this.getDecimalForValue(t[0]),s=1===t.length?1-e:(this.getDecimalForValue(t[1])-e)/2,i=this.getDecimalForValue(t[t.length-1]),n=1===t.length?i:(i-this.getDecimalForValue(t[t.length-2]))/2);const o=t.length<3?.5:.25;s=Z(s,0,o),n=Z(n,0,o),this._offsets={start:s,end:n,factor:1/(s+1+n)}}_generate(){const t=this._adapter,e=this.min,i=this.max,s=this.options,n=s.time,o=n.unit||Bo(n.minUnit,e,i,this._getLabelCapacity(e)),a=l(s.ticks.stepSize,1),r="week"===o&&n.isoWeekday,h=N(r)||!0===r,c={};let d,u,f=e;if(h&&(f=+t.startOf(f,"isoWeek",r)),f=+t.startOf(f,h?"day":o),t.diff(i,e,o)>1e5*a)throw new Error(e+" and "+i+" are too far apart with stepSize of "+a+" "+o);const g="data"===s.ticks.source&&this.getDataTimestamps();for(d=f,u=0;d<i;d=+t.add(d,a,o),u++)Wo(c,d,g);return d!==i&&"ticks"!==s.bounds&&1!==u||Wo(c,d,g),Object.keys(c).sort(Fo).map((t=>+t))}getLabelForValue(t){const e=this._adapter,i=this.options.time;return i.tooltipFormat?e.format(t,i.tooltipFormat):e.format(t,i.displayFormats.datetime)}format(t,e){const i=this.options.time.displayFormats,s=this._unit,n=e||i[s];return this._adapter.format(t,n)}_tickFormatFunction(t,e,i,s){const n=this.options,o=n.ticks.callback;if(o)return d(o,[t,e,i],this);const a=n.time.displayFormats,r=this._unit,l=this._majorUnit,h=r&&a[r],c=l&&a[l],u=i[e],f=l&&c&&u&&u.major;return this._adapter.format(t,s||(f?c:h))}generateTickLabels(t){let e,i,s;for(e=0,i=t.length;e<i;++e)s=t[e],s.label=this._tickFormatFunction(s.value,e,t)}getDecimalForValue(t){return null===t?NaN:(t-this.min)/(this.max-this.min)}getPixelForValue(t){const e=this._offsets,i=this.getDecimalForValue(t);return this.getPixelForDecimal((e.start+i)*e.factor)}getValueForPixel(t){const e=this._offsets,i=this.getDecimalForPixel(t)/e.factor-e.end;return this.min+i*(this.max-this.min)}_getLabelSize(t){const e=this.options.ticks,i=this.ctx.measureText(t).width,s=$(this.isHorizontal()?e.maxRotation:e.minRotation),n=Math.cos(s),o=Math.sin(s),a=this._resolveTickFontOptions(0).size;return{w:i*n+a*o,h:i*o+a*n}}_getLabelCapacity(t){const e=this.options.time,i=e.displayFormats,s=i[e.unit]||i.millisecond,n=this._tickFormatFunction(t,0,No(this,[t],this._majorUnit),s),o=this._getLabelSize(n),a=Math.floor(this.isHorizontal()?this.width/o.w:this.height/o.h)-1;return a>0?a:1}getDataTimestamps(){let t,e,i=this._cache.data||[];if(i.length)return i;const s=this.getMatchingVisibleMetas();if(this._normalized&&s.length)return this._cache.data=s[0].controller.getAllParsedValues(this);for(t=0,e=s.length;t<e;++t)i=i.concat(s[t].controller.getAllParsedValues(this));return this._cache.data=this.normalize(i)}getLabelTimestamps(){const t=this._cache.labels||[];let e,i;if(t.length)return t;const s=this.getLabels();for(e=0,i=s.length;e<i;++e)t.push(Vo(this,s[e]));return this._cache.labels=this._normalized?t:this.normalize(t)}normalize(t){return lt(t.sort(Fo))}}function jo(t,e,i){let s,n,o,a,r=0,l=t.length-1;i?(e>=t[r].pos&&e<=t[l].pos&&({lo:r,hi:l}=it(t,"pos",e)),({pos:s,time:o}=t[r]),({pos:n,time:a}=t[l])):(e>=t[r].time&&e<=t[l].time&&({lo:r,hi:l}=it(t,"time",e)),({time:s,pos:o}=t[r]),({time:n,pos:a}=t[l]));const h=n-s;return h?o+(a-o)*(e-s)/h:o}var $o=Object.freeze({__proto__:null,CategoryScale:class extends tn{static id="category";static defaults={ticks:{callback:mo}};constructor(t){super(t),this._startValue=void 0,this._valueRange=0,this._addedLabels=[]}init(t){const e=this._addedLabels;if(e.length){const t=this.getLabels();for(const{index:i,label:s}of e)t[i]===s&&t.splice(i,1);this._addedLabels=[]}super.init(t)}parse(t,e){if(s(t))return null;const i=this.getLabels();return((t,e)=>null===t?null:Z(Math.round(t),0,e))(e=isFinite(e)&&i[e]===t?e:po(i,t,l(e,t),this._addedLabels),i.length-1)}determineDataLimits(){const{minDefined:t,maxDefined:e}=this.getUserBounds();let{min:i,max:s}=this.getMinMax(!0);"ticks"===this.options.bounds&&(t||(i=0),e||(s=this.getLabels().length-1)),this.min=i,this.max=s}buildTicks(){const t=this.min,e=this.max,i=this.options.offset,s=[];let n=this.getLabels();n=0===t&&e===n.length-1?n:n.slice(t,e+1),this._valueRange=Math.max(n.length-(i?0:1),1),this._startValue=this.min-(i?.5:0);for(let i=t;i<=e;i++)s.push({value:i});return s}getLabelForValue(t){return mo.call(this,t)}configure(){super.configure(),this.isHorizontal()||(this._reversePixels=!this._reversePixels)}getPixelForValue(t){return"number"!=typeof t&&(t=this.parse(t)),null===t?NaN:this.getPixelForDecimal((t-this._startValue)/this._valueRange)}getPixelForTick(t){const e=this.ticks;return t<0||t>e.length-1?null:this.getPixelForValue(e[t].value)}getValueForPixel(t){return Math.round(this._startValue+this.getDecimalForPixel(t)*this._valueRange)}getBasePixel(){return this.bottom}},LinearScale:_o,LogarithmicScale:So,RadialLinearScale:Ro,TimeScale:Ho,TimeSeriesScale:class extends Ho{static id="timeseries";static defaults=Ho.defaults;constructor(t){super(t),this._table=[],this._minPos=void 0,this._tableRange=void 0}initOffsets(){const t=this._getTimestampsForTable(),e=this._table=this.buildLookupTable(t);this._minPos=jo(e,this.min),this._tableRange=jo(e,this.max)-this._minPos,super.initOffsets(t)}buildLookupTable(t){const{min:e,max:i}=this,s=[],n=[];let o,a,r,l,h;for(o=0,a=t.length;o<a;++o)l=t[o],l>=e&&l<=i&&s.push(l);if(s.length<2)return[{time:e,pos:0},{time:i,pos:1}];for(o=0,a=s.length;o<a;++o)h=s[o+1],r=s[o-1],l=s[o],Math.round((h+r)/2)!==l&&n.push({time:l,pos:o/(a-1)});return n}_generate(){const t=this.min,e=this.max;let i=super.getDataTimestamps();return i.includes(t)&&i.length||i.splice(0,0,t),i.includes(e)&&1!==i.length||i.push(e),i.sort(((t,e)=>t-e))}_getTimestampsForTable(){let t=this._cache.all||[];if(t.length)return t;const e=this.getDataTimestamps(),i=this.getLabelTimestamps();return t=e.length&&i.length?this.normalize(e.concat(i)):e.length?e:i,t=this._cache.all=t,t}getDecimalForValue(t){return(jo(this._table,t)-this._minPos)/this._tableRange}getValueForPixel(t){const e=this._offsets,i=this.getDecimalForPixel(t)/e.factor-e.end;return jo(this._table,i*this._tableRange+this._minPos,!0)}}});const Yo=["rgb(54, 162, 235)","rgb(255, 99, 132)","rgb(255, 159, 64)","rgb(255, 205, 86)","rgb(75, 192, 192)","rgb(153, 102, 255)","rgb(201, 203, 207)"],Uo=Yo.map((t=>t.replace("rgb(","rgba(").replace(")",", 0.5)")));function Xo(t){return Yo[t%Yo.length]}function qo(t){return Uo[t%Uo.length]}function Ko(t){let e=0;return(i,s)=>{const n=t.getDatasetMeta(s).controller;n instanceof $n?e=function(t,e){return t.backgroundColor=t.data.map((()=>Xo(e++))),e}(i,e):n instanceof Yn?e=function(t,e){return t.backgroundColor=t.data.map((()=>qo(e++))),e}(i,e):n&&(e=function(t,e){return t.borderColor=Xo(e),t.backgroundColor=qo(e),++e}(i,e))}}function Go(t){let e;for(e in t)if(t[e].borderColor||t[e].backgroundColor)return!0;return!1}var Jo={id:"colors",defaults:{enabled:!0,forceOverride:!1},beforeLayout(t,e,i){if(!i.enabled)return;const{data:{datasets:s},options:n}=t.config,{elements:o}=n,a=Go(s)||(r=n)&&(r.borderColor||r.backgroundColor)||o&&Go(o)||"rgba(0,0,0,0.1)"!==ue.borderColor||"rgba(0,0,0,0.1)"!==ue.backgroundColor;var r;if(!i.forceOverride&&a)return;const l=Ko(t);s.forEach(l)}};function Zo(t){if(t._decimated){const e=t._data;delete t._decimated,delete t._data,Object.defineProperty(t,"data",{configurable:!0,enumerable:!0,writable:!0,value:e})}}function Qo(t){t.data.datasets.forEach((t=>{Zo(t)}))}var ta={id:"decimation",defaults:{algorithm:"min-max",enabled:!1},beforeElementsUpdate:(t,e,i)=>{if(!i.enabled)return void Qo(t);const n=t.width;t.data.datasets.forEach(((e,o)=>{const{_data:a,indexAxis:r}=e,l=t.getDatasetMeta(o),h=a||e.data;if("y"===Pi([r,t.options.indexAxis]))return;if(!l.controller.supportsDecimation)return;const c=t.scales[l.xAxisID];if("linear"!==c.type&&"time"!==c.type)return;if(t.options.parsing)return;let{start:d,count:u}=function(t,e){const i=e.length;let s,n=0;const{iScale:o}=t,{min:a,max:r,minDefined:l,maxDefined:h}=o.getUserBounds();return l&&(n=Z(it(e,o.axis,a).lo,0,i-1)),s=h?Z(it(e,o.axis,r).hi+1,n,i)-n:i-n,{start:n,count:s}}(l,h);if(u<=(i.threshold||4*n))return void Zo(e);let f;switch(s(a)&&(e._data=h,delete e.data,Object.defineProperty(e,"data",{configurable:!0,enumerable:!0,get:function(){return this._decimated},set:function(t){this._data=t}})),i.algorithm){case"lttb":f=function(t,e,i,s,n){const o=n.samples||s;if(o>=i)return t.slice(e,e+i);const a=[],r=(i-2)/(o-2);let l=0;const h=e+i-1;let c,d,u,f,g,p=e;for(a[l++]=t[p],c=0;c<o-2;c++){let s,n=0,o=0;const h=Math.floor((c+1)*r)+1+e,m=Math.min(Math.floor((c+2)*r)+1,i)+e,x=m-h;for(s=h;s<m;s++)n+=t[s].x,o+=t[s].y;n/=x,o/=x;const b=Math.floor(c*r)+1+e,_=Math.min(Math.floor((c+1)*r)+1,i)+e,{x:y,y:v}=t[p];for(u=f=-1,s=b;s<_;s++)f=.5*Math.abs((y-n)*(t[s].y-v)-(y-t[s].x)*(o-v)),f>u&&(u=f,d=t[s],g=s);a[l++]=d,p=g}return a[l++]=t[h],a}(h,d,u,n,i);break;case"min-max":f=function(t,e,i,n){let o,a,r,l,h,c,d,u,f,g,p=0,m=0;const x=[],b=e+i-1,_=t[e].x,y=t[b].x-_;for(o=e;o<e+i;++o){a=t[o],r=(a.x-_)/y*n,l=a.y;const e=0|r;if(e===h)l<f?(f=l,c=o):l>g&&(g=l,d=o),p=(m*p+a.x)/++m;else{const i=o-1;if(!s(c)&&!s(d)){const e=Math.min(c,d),s=Math.max(c,d);e!==u&&e!==i&&x.push({...t[e],x:p}),s!==u&&s!==i&&x.push({...t[s],x:p})}o>0&&i!==u&&x.push(t[i]),x.push(a),h=e,m=0,f=g=l,c=d=u=o}}return x}(h,d,u,n);break;default:throw new Error(`Unsupported decimation algorithm '${i.algorithm}'`)}e._decimated=f}))},destroy(t){Qo(t)}};function ea(t,e,i,s){if(s)return;let n=e[t],o=i[t];return"angle"===t&&(n=G(n),o=G(o)),{property:t,start:n,end:o}}function ia(t,e,i){for(;e>t;e--){const t=i[e];if(!isNaN(t.x)&&!isNaN(t.y))break}return e}function sa(t,e,i,s){return t&&e?s(t[i],e[i]):t?t[i]:e?e[i]:0}function na(t,e){let i=[],s=!1;return n(t)?(s=!0,i=t):i=function(t,e){const{x:i=null,y:s=null}=t||{},n=e.points,o=[];return e.segments.forEach((({start:t,end:e})=>{e=ia(t,e,n);const a=n[t],r=n[e];null!==s?(o.push({x:a.x,y:s}),o.push({x:r.x,y:s})):null!==i&&(o.push({x:i,y:a.y}),o.push({x:i,y:r.y}))})),o}(t,e),i.length?new oo({points:i,options:{tension:0},_loop:s,_fullLoop:s}):null}function oa(t){return t&&!1!==t.fill}function aa(t,e,i){let s=t[e].fill;const n=[e];let o;if(!i)return s;for(;!1!==s&&-1===n.indexOf(s);){if(!a(s))return s;if(o=t[s],!o)return!1;if(o.visible)return s;n.push(s),s=o.fill}return!1}function ra(t,e,i){const s=function(t){const e=t.options,i=e.fill;let s=l(i&&i.target,i);void 0===s&&(s=!!e.backgroundColor);if(!1===s||null===s)return!1;if(!0===s)return"origin";return s}(t);if(o(s))return!isNaN(s.value)&&s;let n=parseFloat(s);return a(n)&&Math.floor(n)===n?function(t,e,i,s){"-"!==t&&"+"!==t||(i=e+i);if(i===e||i<0||i>=s)return!1;return i}(s[0],e,n,i):["origin","start","end","stack","shape"].indexOf(s)>=0&&s}function la(t,e,i){const s=[];for(let n=0;n<i.length;n++){const o=i[n],{first:a,last:r,point:l}=ha(o,e,"x");if(!(!l||a&&r))if(a)s.unshift(l);else if(t.push(l),!r)break}t.push(...s)}function ha(t,e,i){const s=t.interpolate(e,i);if(!s)return{};const n=s[i],o=t.segments,a=t.points;let r=!1,l=!1;for(let t=0;t<o.length;t++){const e=o[t],s=a[e.start][i],h=a[e.end][i];if(tt(n,s,h)){r=n===s,l=n===h;break}}return{first:r,last:l,point:s}}class ca{constructor(t){this.x=t.x,this.y=t.y,this.radius=t.radius}pathSegment(t,e,i){const{x:s,y:n,radius:o}=this;return e=e||{start:0,end:O},t.arc(s,n,o,e.end,e.start,!0),!i.bounds}interpolate(t){const{x:e,y:i,radius:s}=this,n=t.angle;return{x:e+Math.cos(n)*s,y:i+Math.sin(n)*s,angle:n}}}function da(t){const{chart:e,fill:i,line:s}=t;if(a(i))return function(t,e){const i=t.getDatasetMeta(e),s=i&&t.isDatasetVisible(e);return s?i.dataset:null}(e,i);if("stack"===i)return function(t){const{scale:e,index:i,line:s}=t,n=[],o=s.segments,a=s.points,r=function(t,e){const i=[],s=t.getMatchingVisibleMetas("line");for(let t=0;t<s.length;t++){const n=s[t];if(n.index===e)break;n.hidden||i.unshift(n.dataset)}return i}(e,i);r.push(na({x:null,y:e.bottom},s));for(let t=0;t<o.length;t++){const e=o[t];for(let t=e.start;t<=e.end;t++)la(n,a[t],r)}return new oo({points:n,options:{}})}(t);if("shape"===i)return!0;const n=function(t){const e=t.scale||{};if(e.getPointPositionForValue)return function(t){const{scale:e,fill:i}=t,s=e.options,n=e.getLabels().length,a=s.reverse?e.max:e.min,r=function(t,e,i){let s;return s="start"===t?i:"end"===t?e.options.reverse?e.min:e.max:o(t)?t.value:e.getBaseValue(),s}(i,e,a),l=[];if(s.grid.circular){const t=e.getPointPositionForValue(0,a);return new ca({x:t.x,y:t.y,radius:e.getDistanceFromCenterForValue(r)})}for(let t=0;t<n;++t)l.push(e.getPointPositionForValue(t,r));return l}(t);return function(t){const{scale:e={},fill:i}=t,s=function(t,e){let i=null;return"start"===t?i=e.bottom:"end"===t?i=e.top:o(t)?i=e.getPixelForValue(t.value):e.getBasePixel&&(i=e.getBasePixel()),i}(i,e);if(a(s)){const t=e.isHorizontal();return{x:t?s:null,y:t?null:s}}return null}(t)}(t);return n instanceof ca?n:na(n,s)}function ua(t,e,i){const s=da(e),{chart:n,index:o,line:a,scale:r,axis:l}=e,h=a.options,c=h.fill,d=h.backgroundColor,{above:u=d,below:f=d}=c||{},g=n.getDatasetMeta(o),p=Ni(n,g);s&&a.points.length&&(Ie(t,i),function(t,e){const{line:i,target:s,above:n,below:o,area:a,scale:r,clip:l}=e,h=i._loop?"angle":e.axis;t.save();let c=o;o!==n&&("x"===h?(fa(t,s,a.top),pa(t,{line:i,target:s,color:n,scale:r,property:h,clip:l}),t.restore(),t.save(),fa(t,s,a.bottom)):"y"===h&&(ga(t,s,a.left),pa(t,{line:i,target:s,color:o,scale:r,property:h,clip:l}),t.restore(),t.save(),ga(t,s,a.right),c=n));pa(t,{line:i,target:s,color:c,scale:r,property:h,clip:l}),t.restore()}(t,{line:a,target:s,above:u,below:f,area:i,scale:r,axis:l,clip:p}),ze(t))}function fa(t,e,i){const{segments:s,points:n}=e;let o=!0,a=!1;t.beginPath();for(const r of s){const{start:s,end:l}=r,h=n[s],c=n[ia(s,l,n)];o?(t.moveTo(h.x,h.y),o=!1):(t.lineTo(h.x,i),t.lineTo(h.x,h.y)),a=!!e.pathSegment(t,r,{move:a}),a?t.closePath():t.lineTo(c.x,i)}t.lineTo(e.first().x,i),t.closePath(),t.clip()}function ga(t,e,i){const{segments:s,points:n}=e;let o=!0,a=!1;t.beginPath();for(const r of s){const{start:s,end:l}=r,h=n[s],c=n[ia(s,l,n)];o?(t.moveTo(h.x,h.y),o=!1):(t.lineTo(i,h.y),t.lineTo(h.x,h.y)),a=!!e.pathSegment(t,r,{move:a}),a?t.closePath():t.lineTo(i,c.y)}t.lineTo(i,e.first().y),t.closePath(),t.clip()}function pa(t,e){const{line:i,target:s,property:n,color:o,scale:a,clip:r}=e,l=function(t,e,i){const s=t.segments,n=t.points,o=e.points,a=[];for(const t of s){let{start:s,end:r}=t;r=ia(s,r,n);const l=ea(i,n[s],n[r],t.loop);if(!e.segments){a.push({source:t,target:l,start:n[s],end:n[r]});continue}const h=Ii(e,l);for(const e of h){const s=ea(i,o[e.start],o[e.end],e.loop),r=Ri(t,n,s);for(const t of r)a.push({source:t,target:e,start:{[i]:sa(l,s,"start",Math.max)},end:{[i]:sa(l,s,"end",Math.min)}})}}return a}(i,s,n);for(const{source:e,target:h,start:c,end:d}of l){const{style:{backgroundColor:l=o}={}}=e,u=!0!==s;t.save(),t.fillStyle=l,ma(t,a,r,u&&ea(n,c,d)),t.beginPath();const f=!!i.pathSegment(t,e);let g;if(u){f?t.closePath():xa(t,s,d,n);const e=!!s.pathSegment(t,h,{move:f,reverse:!0});g=f&&e,g||xa(t,s,c,n)}t.closePath(),t.fill(g?"evenodd":"nonzero"),t.restore()}}function ma(t,e,i,s){const n=e.chart.chartArea,{property:o,start:a,end:r}=s||{};if("x"===o||"y"===o){let e,s,l,h;"x"===o?(e=a,s=n.top,l=r,h=n.bottom):(e=n.left,s=a,l=n.right,h=r),t.beginPath(),i&&(e=Math.max(e,i.left),l=Math.min(l,i.right),s=Math.max(s,i.top),h=Math.min(h,i.bottom)),t.rect(e,s,l-e,h-s),t.clip()}}function xa(t,e,i,s){const n=e.interpolate(i,s);n&&t.lineTo(n.x,n.y)}var ba={id:"filler",afterDatasetsUpdate(t,e,i){const s=(t.data.datasets||[]).length,n=[];let o,a,r,l;for(a=0;a<s;++a)o=t.getDatasetMeta(a),r=o.dataset,l=null,r&&r.options&&r instanceof oo&&(l={visible:t.isDatasetVisible(a),index:a,fill:ra(r,a,s),chart:t,axis:o.controller.options.indexAxis,scale:o.vScale,line:r}),o.$filler=l,n.push(l);for(a=0;a<s;++a)l=n[a],l&&!1!==l.fill&&(l.fill=aa(n,a,i.propagate))},beforeDraw(t,e,i){const s="beforeDraw"===i.drawTime,n=t.getSortedVisibleDatasetMetas(),o=t.chartArea;for(let e=n.length-1;e>=0;--e){const i=n[e].$filler;i&&(i.line.updateControlPoints(o,i.axis),s&&i.fill&&ua(t.ctx,i,o))}},beforeDatasetsDraw(t,e,i){if("beforeDatasetsDraw"!==i.drawTime)return;const s=t.getSortedVisibleDatasetMetas();for(let e=s.length-1;e>=0;--e){const i=s[e].$filler;oa(i)&&ua(t.ctx,i,t.chartArea)}},beforeDatasetDraw(t,e,i){const s=e.meta.$filler;oa(s)&&"beforeDatasetDraw"===i.drawTime&&ua(t.ctx,s,t.chartArea)},defaults:{propagate:!0,drawTime:"beforeDatasetDraw"}};const _a=(t,e)=>{let{boxHeight:i=e,boxWidth:s=e}=t;return t.usePointStyle&&(i=Math.min(i,e),s=t.pointStyleWidth||Math.min(s,e)),{boxWidth:s,boxHeight:i,itemHeight:Math.max(e,i)}};class ya extends $s{constructor(t){super(),this._added=!1,this.legendHitBoxes=[],this._hoveredItem=null,this.doughnutMode=!1,this.chart=t.chart,this.options=t.options,this.ctx=t.ctx,this.legendItems=void 0,this.columnSizes=void 0,this.lineWidths=void 0,this.maxHeight=void 0,this.maxWidth=void 0,this.top=void 0,this.bottom=void 0,this.left=void 0,this.right=void 0,this.height=void 0,this.width=void 0,this._margins=void 0,this.position=void 0,this.weight=void 0,this.fullSize=void 0}update(t,e,i){this.maxWidth=t,this.maxHeight=e,this._margins=i,this.setDimensions(),this.buildLabels(),this.fit()}setDimensions(){this.isHorizontal()?(this.width=this.maxWidth,this.left=this._margins.left,this.right=this.width):(this.height=this.maxHeight,this.top=this._margins.top,this.bottom=this.height)}buildLabels(){const t=this.options.labels||{};let e=d(t.generateLabels,[this.chart],this)||[];t.filter&&(e=e.filter((e=>t.filter(e,this.chart.data)))),t.sort&&(e=e.sort(((e,i)=>t.sort(e,i,this.chart.data)))),this.options.reverse&&e.reverse(),this.legendItems=e}fit(){const{options:t,ctx:e}=this;if(!t.display)return void(this.width=this.height=0);const i=t.labels,s=Si(i.font),n=s.size,o=this._computeTitleHeight(),{boxWidth:a,itemHeight:r}=_a(i,n);let l,h;e.font=s.string,this.isHorizontal()?(l=this.maxWidth,h=this._fitRows(o,n,a,r)+10):(h=this.maxHeight,l=this._fitCols(o,s,a,r)+10),this.width=Math.min(l,t.maxWidth||this.maxWidth),this.height=Math.min(h,t.maxHeight||this.maxHeight)}_fitRows(t,e,i,s){const{ctx:n,maxWidth:o,options:{labels:{padding:a}}}=this,r=this.legendHitBoxes=[],l=this.lineWidths=[0],h=s+a;let c=t;n.textAlign="left",n.textBaseline="middle";let d=-1,u=-h;return this.legendItems.forEach(((t,f)=>{const g=i+e/2+n.measureText(t.text).width;(0===f||l[l.length-1]+g+2*a>o)&&(c+=h,l[l.length-(f>0?0:1)]=0,u+=h,d++),r[f]={left:0,top:u,row:d,width:g,height:s},l[l.length-1]+=g+a})),c}_fitCols(t,e,i,s){const{ctx:n,maxHeight:o,options:{labels:{padding:a}}}=this,r=this.legendHitBoxes=[],l=this.columnSizes=[],h=o-t;let c=a,d=0,u=0,f=0,g=0;return this.legendItems.forEach(((t,o)=>{const{itemWidth:p,itemHeight:m}=function(t,e,i,s,n){const o=function(t,e,i,s){let n=t.text;n&&"string"!=typeof n&&(n=n.reduce(((t,e)=>t.length>e.length?t:e)));return e+i.size/2+s.measureText(n).width}(s,t,e,i),a=function(t,e,i){let s=t;"string"!=typeof e.text&&(s=va(e,i));return s}(n,s,e.lineHeight);return{itemWidth:o,itemHeight:a}}(i,e,n,t,s);o>0&&u+m+2*a>h&&(c+=d+a,l.push({width:d,height:u}),f+=d+a,g++,d=u=0),r[o]={left:f,top:u,col:g,width:p,height:m},d=Math.max(d,p),u+=m+a})),c+=d,l.push({width:d,height:u}),c}adjustHitBoxes(){if(!this.options.display)return;const t=this._computeTitleHeight(),{legendHitBoxes:e,options:{align:i,labels:{padding:s},rtl:n}}=this,o=Oi(n,this.left,this.width);if(this.isHorizontal()){let n=0,a=ft(i,this.left+s,this.right-this.lineWidths[n]);for(const r of e)n!==r.row&&(n=r.row,a=ft(i,this.left+s,this.right-this.lineWidths[n])),r.top+=this.top+t+s,r.left=o.leftForLtr(o.x(a),r.width),a+=r.width+s}else{let n=0,a=ft(i,this.top+t+s,this.bottom-this.columnSizes[n].height);for(const r of e)r.col!==n&&(n=r.col,a=ft(i,this.top+t+s,this.bottom-this.columnSizes[n].height)),r.top=a,r.left+=this.left+s,r.left=o.leftForLtr(o.x(r.left),r.width),a+=r.height+s}}isHorizontal(){return"top"===this.options.position||"bottom"===this.options.position}draw(){if(this.options.display){const t=this.ctx;Ie(t,this),this._draw(),ze(t)}}_draw(){const{options:t,columnSizes:e,lineWidths:i,ctx:s}=this,{align:n,labels:o}=t,a=ue.color,r=Oi(t.rtl,this.left,this.width),h=Si(o.font),{padding:c}=o,d=h.size,u=d/2;let f;this.drawTitle(),s.textAlign=r.textAlign("left"),s.textBaseline="middle",s.lineWidth=.5,s.font=h.string;const{boxWidth:g,boxHeight:p,itemHeight:m}=_a(o,d),x=this.isHorizontal(),b=this._computeTitleHeight();f=x?{x:ft(n,this.left+c,this.right-i[0]),y:this.top+c+b,line:0}:{x:this.left+c,y:ft(n,this.top+b+c,this.bottom-e[0].height),line:0},Ai(this.ctx,t.textDirection);const _=m+c;this.legendItems.forEach(((y,v)=>{s.strokeStyle=y.fontColor,s.fillStyle=y.fontColor;const M=s.measureText(y.text).width,w=r.textAlign(y.textAlign||(y.textAlign=o.textAlign)),k=g+u+M;let S=f.x,P=f.y;r.setWidth(this.width),x?v>0&&S+k+c>this.right&&(P=f.y+=_,f.line++,S=f.x=ft(n,this.left+c,this.right-i[f.line])):v>0&&P+_>this.bottom&&(S=f.x=S+e[f.line].width+c,f.line++,P=f.y=ft(n,this.top+b+c,this.bottom-e[f.line].height));if(function(t,e,i){if(isNaN(g)||g<=0||isNaN(p)||p<0)return;s.save();const n=l(i.lineWidth,1);if(s.fillStyle=l(i.fillStyle,a),s.lineCap=l(i.lineCap,"butt"),s.lineDashOffset=l(i.lineDashOffset,0),s.lineJoin=l(i.lineJoin,"miter"),s.lineWidth=n,s.strokeStyle=l(i.strokeStyle,a),s.setLineDash(l(i.lineDash,[])),o.usePointStyle){const a={radius:p*Math.SQRT2/2,pointStyle:i.pointStyle,rotation:i.rotation,borderWidth:n},l=r.xPlus(t,g/2);Ee(s,a,l,e+u,o.pointStyleWidth&&g)}else{const o=e+Math.max((d-p)/2,0),a=r.leftForLtr(t,g),l=wi(i.borderRadius);s.beginPath(),Object.values(l).some((t=>0!==t))?He(s,{x:a,y:o,w:g,h:p,radius:l}):s.rect(a,o,g,p),s.fill(),0!==n&&s.stroke()}s.restore()}(r.x(S),P,y),S=gt(w,S+g+u,x?S+k:this.right,t.rtl),function(t,e,i){Ne(s,i.text,t,e+m/2,h,{strikethrough:i.hidden,textAlign:r.textAlign(i.textAlign)})}(r.x(S),P,y),x)f.x+=k+c;else if("string"!=typeof y.text){const t=h.lineHeight;f.y+=va(y,t)+c}else f.y+=_})),Ti(this.ctx,t.textDirection)}drawTitle(){const t=this.options,e=t.title,i=Si(e.font),s=ki(e.padding);if(!e.display)return;const n=Oi(t.rtl,this.left,this.width),o=this.ctx,a=e.position,r=i.size/2,l=s.top+r;let h,c=this.left,d=this.width;if(this.isHorizontal())d=Math.max(...this.lineWidths),h=this.top+l,c=ft(t.align,c,this.right-d);else{const e=this.columnSizes.reduce(((t,e)=>Math.max(t,e.height)),0);h=l+ft(t.align,this.top,this.bottom-e-t.labels.padding-this._computeTitleHeight())}const u=ft(a,c,c+d);o.textAlign=n.textAlign(ut(a)),o.textBaseline="middle",o.strokeStyle=e.color,o.fillStyle=e.color,o.font=i.string,Ne(o,e.text,u,h,i)}_computeTitleHeight(){const t=this.options.title,e=Si(t.font),i=ki(t.padding);return t.display?e.lineHeight+i.height:0}_getLegendItemAt(t,e){let i,s,n;if(tt(t,this.left,this.right)&&tt(e,this.top,this.bottom))for(n=this.legendHitBoxes,i=0;i<n.length;++i)if(s=n[i],tt(t,s.left,s.left+s.width)&&tt(e,s.top,s.top+s.height))return this.legendItems[i];return null}handleEvent(t){const e=this.options;if(!function(t,e){if(("mousemove"===t||"mouseout"===t)&&(e.onHover||e.onLeave))return!0;if(e.onClick&&("click"===t||"mouseup"===t))return!0;return!1}(t.type,e))return;const i=this._getLegendItemAt(t.x,t.y);if("mousemove"===t.type||"mouseout"===t.type){const o=this._hoveredItem,a=(n=i,null!==(s=o)&&null!==n&&s.datasetIndex===n.datasetIndex&&s.index===n.index);o&&!a&&d(e.onLeave,[t,o,this],this),this._hoveredItem=i,i&&!a&&d(e.onHover,[t,i,this],this)}else i&&d(e.onClick,[t,i,this],this);var s,n}}function va(t,e){return e*(t.text?t.text.length:0)}var Ma={id:"legend",_element:ya,start(t,e,i){const s=t.legend=new ya({ctx:t.ctx,options:i,chart:t});ls.configure(t,s,i),ls.addBox(t,s)},stop(t){ls.removeBox(t,t.legend),delete t.legend},beforeUpdate(t,e,i){const s=t.legend;ls.configure(t,s,i),s.options=i},afterUpdate(t){const e=t.legend;e.buildLabels(),e.adjustHitBoxes()},afterEvent(t,e){e.replay||t.legend.handleEvent(e.event)},defaults:{display:!0,position:"top",align:"center",fullSize:!0,reverse:!1,weight:1e3,onClick(t,e,i){const s=e.datasetIndex,n=i.chart;n.isDatasetVisible(s)?(n.hide(s),e.hidden=!0):(n.show(s),e.hidden=!1)},onHover:null,onLeave:null,labels:{color:t=>t.chart.options.color,boxWidth:40,padding:10,generateLabels(t){const e=t.data.datasets,{labels:{usePointStyle:i,pointStyle:s,textAlign:n,color:o,useBorderRadius:a,borderRadius:r}}=t.legend.options;return t._getSortedDatasetMetas().map((t=>{const l=t.controller.getStyle(i?0:void 0),h=ki(l.borderWidth);return{text:e[t.index].label,fillStyle:l.backgroundColor,fontColor:o,hidden:!t.visible,lineCap:l.borderCapStyle,lineDash:l.borderDash,lineDashOffset:l.borderDashOffset,lineJoin:l.borderJoinStyle,lineWidth:(h.width+h.height)/4,strokeStyle:l.borderColor,pointStyle:s||l.pointStyle,rotation:l.rotation,textAlign:n||l.textAlign,borderRadius:a&&(r||l.borderRadius),datasetIndex:t.index}}),this)}},title:{color:t=>t.chart.options.color,display:!1,position:"center",text:""}},descriptors:{_scriptable:t=>!t.startsWith("on"),labels:{_scriptable:t=>!["generateLabels","filter","sort"].includes(t)}}};class wa extends $s{constructor(t){super(),this.chart=t.chart,this.options=t.options,this.ctx=t.ctx,this._padding=void 0,this.top=void 0,this.bottom=void 0,this.left=void 0,this.right=void 0,this.width=void 0,this.height=void 0,this.position=void 0,this.weight=void 0,this.fullSize=void 0}update(t,e){const i=this.options;if(this.left=0,this.top=0,!i.display)return void(this.width=this.height=this.right=this.bottom=0);this.width=this.right=t,this.height=this.bottom=e;const s=n(i.text)?i.text.length:1;this._padding=ki(i.padding);const o=s*Si(i.font).lineHeight+this._padding.height;this.isHorizontal()?this.height=o:this.width=o}isHorizontal(){const t=this.options.position;return"top"===t||"bottom"===t}_drawArgs(t){const{top:e,left:i,bottom:s,right:n,options:o}=this,a=o.align;let r,l,h,c=0;return this.isHorizontal()?(l=ft(a,i,n),h=e+t,r=n-i):("left"===o.position?(l=i+t,h=ft(a,s,e),c=-.5*C):(l=n-t,h=ft(a,e,s),c=.5*C),r=s-e),{titleX:l,titleY:h,maxWidth:r,rotation:c}}draw(){const t=this.ctx,e=this.options;if(!e.display)return;const i=Si(e.font),s=i.lineHeight/2+this._padding.top,{titleX:n,titleY:o,maxWidth:a,rotation:r}=this._drawArgs(s);Ne(t,e.text,0,0,i,{color:e.color,maxWidth:a,rotation:r,textAlign:ut(e.align),textBaseline:"middle",translation:[n,o]})}}var ka={id:"title",_element:wa,start(t,e,i){!function(t,e){const i=new wa({ctx:t.ctx,options:e,chart:t});ls.configure(t,i,e),ls.addBox(t,i),t.titleBlock=i}(t,i)},stop(t){const e=t.titleBlock;ls.removeBox(t,e),delete t.titleBlock},beforeUpdate(t,e,i){const s=t.titleBlock;ls.configure(t,s,i),s.options=i},defaults:{align:"center",display:!1,font:{weight:"bold"},fullSize:!0,padding:10,position:"top",text:"",weight:2e3},defaultRoutes:{color:"color"},descriptors:{_scriptable:!0,_indexable:!1}};const Sa=new WeakMap;var Pa={id:"subtitle",start(t,e,i){const s=new wa({ctx:t.ctx,options:i,chart:t});ls.configure(t,s,i),ls.addBox(t,s),Sa.set(t,s)},stop(t){ls.removeBox(t,Sa.get(t)),Sa.delete(t)},beforeUpdate(t,e,i){const s=Sa.get(t);ls.configure(t,s,i),s.options=i},defaults:{align:"center",display:!1,font:{weight:"normal"},fullSize:!0,padding:0,position:"top",text:"",weight:1500},defaultRoutes:{color:"color"},descriptors:{_scriptable:!0,_indexable:!1}};const Da={average(t){if(!t.length)return!1;let e,i,s=new Set,n=0,o=0;for(e=0,i=t.length;e<i;++e){const i=t[e].element;if(i&&i.hasValue()){const t=i.tooltipPosition();s.add(t.x),n+=t.y,++o}}if(0===o||0===s.size)return!1;return{x:[...s].reduce(((t,e)=>t+e))/s.size,y:n/o}},nearest(t,e){if(!t.length)return!1;let i,s,n,o=e.x,a=e.y,r=Number.POSITIVE_INFINITY;for(i=0,s=t.length;i<s;++i){const s=t[i].element;if(s&&s.hasValue()){const t=q(e,s.getCenterPoint());t<r&&(r=t,n=s)}}if(n){const t=n.tooltipPosition();o=t.x,a=t.y}return{x:o,y:a}}};function Ca(t,e){return e&&(n(e)?Array.prototype.push.apply(t,e):t.push(e)),t}function Oa(t){return("string"==typeof t||t instanceof String)&&t.indexOf("\n")>-1?t.split("\n"):t}function Aa(t,e){const{element:i,datasetIndex:s,index:n}=e,o=t.getDatasetMeta(s).controller,{label:a,value:r}=o.getLabelAndValue(n);return{chart:t,label:a,parsed:o.getParsed(n),raw:t.data.datasets[s].data[n],formattedValue:r,dataset:o.getDataset(),dataIndex:n,datasetIndex:s,element:i}}function Ta(t,e){const i=t.chart.ctx,{body:s,footer:n,title:o}=t,{boxWidth:a,boxHeight:r}=e,l=Si(e.bodyFont),h=Si(e.titleFont),c=Si(e.footerFont),d=o.length,f=n.length,g=s.length,p=ki(e.padding);let m=p.height,x=0,b=s.reduce(((t,e)=>t+e.before.length+e.lines.length+e.after.length),0);if(b+=t.beforeBody.length+t.afterBody.length,d&&(m+=d*h.lineHeight+(d-1)*e.titleSpacing+e.titleMarginBottom),b){m+=g*(e.displayColors?Math.max(r,l.lineHeight):l.lineHeight)+(b-g)*l.lineHeight+(b-1)*e.bodySpacing}f&&(m+=e.footerMarginTop+f*c.lineHeight+(f-1)*e.footerSpacing);let _=0;const y=function(t){x=Math.max(x,i.measureText(t).width+_)};return i.save(),i.font=h.string,u(t.title,y),i.font=l.string,u(t.beforeBody.concat(t.afterBody),y),_=e.displayColors?a+2+e.boxPadding:0,u(s,(t=>{u(t.before,y),u(t.lines,y),u(t.after,y)})),_=0,i.font=c.string,u(t.footer,y),i.restore(),x+=p.width,{width:x,height:m}}function La(t,e,i,s){const{x:n,width:o}=i,{width:a,chartArea:{left:r,right:l}}=t;let h="center";return"center"===s?h=n<=(r+l)/2?"left":"right":n<=o/2?h="left":n>=a-o/2&&(h="right"),function(t,e,i,s){const{x:n,width:o}=s,a=i.caretSize+i.caretPadding;return"left"===t&&n+o+a>e.width||"right"===t&&n-o-a<0||void 0}(h,t,e,i)&&(h="center"),h}function Ea(t,e,i){const s=i.yAlign||e.yAlign||function(t,e){const{y:i,height:s}=e;return i<s/2?"top":i>t.height-s/2?"bottom":"center"}(t,i);return{xAlign:i.xAlign||e.xAlign||La(t,e,i,s),yAlign:s}}function Ra(t,e,i,s){const{caretSize:n,caretPadding:o,cornerRadius:a}=t,{xAlign:r,yAlign:l}=i,h=n+o,{topLeft:c,topRight:d,bottomLeft:u,bottomRight:f}=wi(a);let g=function(t,e){let{x:i,width:s}=t;return"right"===e?i-=s:"center"===e&&(i-=s/2),i}(e,r);const p=function(t,e,i){let{y:s,height:n}=t;return"top"===e?s+=i:s-="bottom"===e?n+i:n/2,s}(e,l,h);return"center"===l?"left"===r?g+=h:"right"===r&&(g-=h):"left"===r?g-=Math.max(c,u)+n:"right"===r&&(g+=Math.max(d,f)+n),{x:Z(g,0,s.width-e.width),y:Z(p,0,s.height-e.height)}}function Ia(t,e,i){const s=ki(i.padding);return"center"===e?t.x+t.width/2:"right"===e?t.x+t.width-s.right:t.x+s.left}function za(t){return Ca([],Oa(t))}function Fa(t,e){const i=e&&e.dataset&&e.dataset.tooltip&&e.dataset.tooltip.callbacks;return i?t.override(i):t}const Va={beforeTitle:e,title(t){if(t.length>0){const e=t[0],i=e.chart.data.labels,s=i?i.length:0;if(this&&this.options&&"dataset"===this.options.mode)return e.dataset.label||"";if(e.label)return e.label;if(s>0&&e.dataIndex<s)return i[e.dataIndex]}return""},afterTitle:e,beforeBody:e,beforeLabel:e,label(t){if(this&&this.options&&"dataset"===this.options.mode)return t.label+": "+t.formattedValue||t.formattedValue;let e=t.dataset.label||"";e&&(e+=": ");const i=t.formattedValue;return s(i)||(e+=i),e},labelColor(t){const e=t.chart.getDatasetMeta(t.datasetIndex).controller.getStyle(t.dataIndex);return{borderColor:e.borderColor,backgroundColor:e.backgroundColor,borderWidth:e.borderWidth,borderDash:e.borderDash,borderDashOffset:e.borderDashOffset,borderRadius:0}},labelTextColor(){return this.options.bodyColor},labelPointStyle(t){const e=t.chart.getDatasetMeta(t.datasetIndex).controller.getStyle(t.dataIndex);return{pointStyle:e.pointStyle,rotation:e.rotation}},afterLabel:e,afterBody:e,beforeFooter:e,footer:e,afterFooter:e};function Ba(t,e,i,s){const n=t[e].call(i,s);return void 0===n?Va[e].call(i,s):n}class Wa extends $s{static positioners=Da;constructor(t){super(),this.opacity=0,this._active=[],this._eventPosition=void 0,this._size=void 0,this._cachedAnimations=void 0,this._tooltipItems=[],this.$animations=void 0,this.$context=void 0,this.chart=t.chart,this.options=t.options,this.dataPoints=void 0,this.title=void 0,this.beforeBody=void 0,this.body=void 0,this.afterBody=void 0,this.footer=void 0,this.xAlign=void 0,this.yAlign=void 0,this.x=void 0,this.y=void 0,this.height=void 0,this.width=void 0,this.caretX=void 0,this.caretY=void 0,this.labelColors=void 0,this.labelPointStyles=void 0,this.labelTextColors=void 0}initialize(t){this.options=t,this._cachedAnimations=void 0,this.$context=void 0}_resolveAnimations(){const t=this._cachedAnimations;if(t)return t;const e=this.chart,i=this.options.setContext(this.getContext()),s=i.enabled&&e.options.animation&&i.animations,n=new Ts(this.chart,s);return s._cacheable&&(this._cachedAnimations=Object.freeze(n)),n}getContext(){return this.$context||(this.$context=(t=this.chart.getContext(),e=this,i=this._tooltipItems,Ci(t,{tooltip:e,tooltipItems:i,type:"tooltip"})));var t,e,i}getTitle(t,e){const{callbacks:i}=e,s=Ba(i,"beforeTitle",this,t),n=Ba(i,"title",this,t),o=Ba(i,"afterTitle",this,t);let a=[];return a=Ca(a,Oa(s)),a=Ca(a,Oa(n)),a=Ca(a,Oa(o)),a}getBeforeBody(t,e){return za(Ba(e.callbacks,"beforeBody",this,t))}getBody(t,e){const{callbacks:i}=e,s=[];return u(t,(t=>{const e={before:[],lines:[],after:[]},n=Fa(i,t);Ca(e.before,Oa(Ba(n,"beforeLabel",this,t))),Ca(e.lines,Ba(n,"label",this,t)),Ca(e.after,Oa(Ba(n,"afterLabel",this,t))),s.push(e)})),s}getAfterBody(t,e){return za(Ba(e.callbacks,"afterBody",this,t))}getFooter(t,e){const{callbacks:i}=e,s=Ba(i,"beforeFooter",this,t),n=Ba(i,"footer",this,t),o=Ba(i,"afterFooter",this,t);let a=[];return a=Ca(a,Oa(s)),a=Ca(a,Oa(n)),a=Ca(a,Oa(o)),a}_createItems(t){const e=this._active,i=this.chart.data,s=[],n=[],o=[];let a,r,l=[];for(a=0,r=e.length;a<r;++a)l.push(Aa(this.chart,e[a]));return t.filter&&(l=l.filter(((e,s,n)=>t.filter(e,s,n,i)))),t.itemSort&&(l=l.sort(((e,s)=>t.itemSort(e,s,i)))),u(l,(e=>{const i=Fa(t.callbacks,e);s.push(Ba(i,"labelColor",this,e)),n.push(Ba(i,"labelPointStyle",this,e)),o.push(Ba(i,"labelTextColor",this,e))})),this.labelColors=s,this.labelPointStyles=n,this.labelTextColors=o,this.dataPoints=l,l}update(t,e){const i=this.options.setContext(this.getContext()),s=this._active;let n,o=[];if(s.length){const t=Da[i.position].call(this,s,this._eventPosition);o=this._createItems(i),this.title=this.getTitle(o,i),this.beforeBody=this.getBeforeBody(o,i),this.body=this.getBody(o,i),this.afterBody=this.getAfterBody(o,i),this.footer=this.getFooter(o,i);const e=this._size=Ta(this,i),a=Object.assign({},t,e),r=Ea(this.chart,i,a),l=Ra(i,a,r,this.chart);this.xAlign=r.xAlign,this.yAlign=r.yAlign,n={opacity:1,x:l.x,y:l.y,width:e.width,height:e.height,caretX:t.x,caretY:t.y}}else 0!==this.opacity&&(n={opacity:0});this._tooltipItems=o,this.$context=void 0,n&&this._resolveAnimations().update(this,n),t&&i.external&&i.external.call(this,{chart:this.chart,tooltip:this,replay:e})}drawCaret(t,e,i,s){const n=this.getCaretPosition(t,i,s);e.lineTo(n.x1,n.y1),e.lineTo(n.x2,n.y2),e.lineTo(n.x3,n.y3)}getCaretPosition(t,e,i){const{xAlign:s,yAlign:n}=this,{caretSize:o,cornerRadius:a}=i,{topLeft:r,topRight:l,bottomLeft:h,bottomRight:c}=wi(a),{x:d,y:u}=t,{width:f,height:g}=e;let p,m,x,b,_,y;return"center"===n?(_=u+g/2,"left"===s?(p=d,m=p-o,b=_+o,y=_-o):(p=d+f,m=p+o,b=_-o,y=_+o),x=p):(m="left"===s?d+Math.max(r,h)+o:"right"===s?d+f-Math.max(l,c)-o:this.caretX,"top"===n?(b=u,_=b-o,p=m-o,x=m+o):(b=u+g,_=b+o,p=m+o,x=m-o),y=b),{x1:p,x2:m,x3:x,y1:b,y2:_,y3:y}}drawTitle(t,e,i){const s=this.title,n=s.length;let o,a,r;if(n){const l=Oi(i.rtl,this.x,this.width);for(t.x=Ia(this,i.titleAlign,i),e.textAlign=l.textAlign(i.titleAlign),e.textBaseline="middle",o=Si(i.titleFont),a=i.titleSpacing,e.fillStyle=i.titleColor,e.font=o.string,r=0;r<n;++r)e.fillText(s[r],l.x(t.x),t.y+o.lineHeight/2),t.y+=o.lineHeight+a,r+1===n&&(t.y+=i.titleMarginBottom-a)}}_drawColorBox(t,e,i,s,n){const a=this.labelColors[i],r=this.labelPointStyles[i],{boxHeight:l,boxWidth:h}=n,c=Si(n.bodyFont),d=Ia(this,"left",n),u=s.x(d),f=l<c.lineHeight?(c.lineHeight-l)/2:0,g=e.y+f;if(n.usePointStyle){const e={radius:Math.min(h,l)/2,pointStyle:r.pointStyle,rotation:r.rotation,borderWidth:1},i=s.leftForLtr(u,h)+h/2,o=g+l/2;t.strokeStyle=n.multiKeyBackground,t.fillStyle=n.multiKeyBackground,Le(t,e,i,o),t.strokeStyle=a.borderColor,t.fillStyle=a.backgroundColor,Le(t,e,i,o)}else{t.lineWidth=o(a.borderWidth)?Math.max(...Object.values(a.borderWidth)):a.borderWidth||1,t.strokeStyle=a.borderColor,t.setLineDash(a.borderDash||[]),t.lineDashOffset=a.borderDashOffset||0;const e=s.leftForLtr(u,h),i=s.leftForLtr(s.xPlus(u,1),h-2),r=wi(a.borderRadius);Object.values(r).some((t=>0!==t))?(t.beginPath(),t.fillStyle=n.multiKeyBackground,He(t,{x:e,y:g,w:h,h:l,radius:r}),t.fill(),t.stroke(),t.fillStyle=a.backgroundColor,t.beginPath(),He(t,{x:i,y:g+1,w:h-2,h:l-2,radius:r}),t.fill()):(t.fillStyle=n.multiKeyBackground,t.fillRect(e,g,h,l),t.strokeRect(e,g,h,l),t.fillStyle=a.backgroundColor,t.fillRect(i,g+1,h-2,l-2))}t.fillStyle=this.labelTextColors[i]}drawBody(t,e,i){const{body:s}=this,{bodySpacing:n,bodyAlign:o,displayColors:a,boxHeight:r,boxWidth:l,boxPadding:h}=i,c=Si(i.bodyFont);let d=c.lineHeight,f=0;const g=Oi(i.rtl,this.x,this.width),p=function(i){e.fillText(i,g.x(t.x+f),t.y+d/2),t.y+=d+n},m=g.textAlign(o);let x,b,_,y,v,M,w;for(e.textAlign=o,e.textBaseline="middle",e.font=c.string,t.x=Ia(this,m,i),e.fillStyle=i.bodyColor,u(this.beforeBody,p),f=a&&"right"!==m?"center"===o?l/2+h:l+2+h:0,y=0,M=s.length;y<M;++y){for(x=s[y],b=this.labelTextColors[y],e.fillStyle=b,u(x.before,p),_=x.lines,a&&_.length&&(this._drawColorBox(e,t,y,g,i),d=Math.max(c.lineHeight,r)),v=0,w=_.length;v<w;++v)p(_[v]),d=c.lineHeight;u(x.after,p)}f=0,d=c.lineHeight,u(this.afterBody,p),t.y-=n}drawFooter(t,e,i){const s=this.footer,n=s.length;let o,a;if(n){const r=Oi(i.rtl,this.x,this.width);for(t.x=Ia(this,i.footerAlign,i),t.y+=i.footerMarginTop,e.textAlign=r.textAlign(i.footerAlign),e.textBaseline="middle",o=Si(i.footerFont),e.fillStyle=i.footerColor,e.font=o.string,a=0;a<n;++a)e.fillText(s[a],r.x(t.x),t.y+o.lineHeight/2),t.y+=o.lineHeight+i.footerSpacing}}drawBackground(t,e,i,s){const{xAlign:n,yAlign:o}=this,{x:a,y:r}=t,{width:l,height:h}=i,{topLeft:c,topRight:d,bottomLeft:u,bottomRight:f}=wi(s.cornerRadius);e.fillStyle=s.backgroundColor,e.strokeStyle=s.borderColor,e.lineWidth=s.borderWidth,e.beginPath(),e.moveTo(a+c,r),"top"===o&&this.drawCaret(t,e,i,s),e.lineTo(a+l-d,r),e.quadraticCurveTo(a+l,r,a+l,r+d),"center"===o&&"right"===n&&this.drawCaret(t,e,i,s),e.lineTo(a+l,r+h-f),e.quadraticCurveTo(a+l,r+h,a+l-f,r+h),"bottom"===o&&this.drawCaret(t,e,i,s),e.lineTo(a+u,r+h),e.quadraticCurveTo(a,r+h,a,r+h-u),"center"===o&&"left"===n&&this.drawCaret(t,e,i,s),e.lineTo(a,r+c),e.quadraticCurveTo(a,r,a+c,r),e.closePath(),e.fill(),s.borderWidth>0&&e.stroke()}_updateAnimationTarget(t){const e=this.chart,i=this.$animations,s=i&&i.x,n=i&&i.y;if(s||n){const i=Da[t.position].call(this,this._active,this._eventPosition);if(!i)return;const o=this._size=Ta(this,t),a=Object.assign({},i,this._size),r=Ea(e,t,a),l=Ra(t,a,r,e);s._to===l.x&&n._to===l.y||(this.xAlign=r.xAlign,this.yAlign=r.yAlign,this.width=o.width,this.height=o.height,this.caretX=i.x,this.caretY=i.y,this._resolveAnimations().update(this,l))}}_willRender(){return!!this.opacity}draw(t){const e=this.options.setContext(this.getContext());let i=this.opacity;if(!i)return;this._updateAnimationTarget(e);const s={width:this.width,height:this.height},n={x:this.x,y:this.y};i=Math.abs(i)<.001?0:i;const o=ki(e.padding),a=this.title.length||this.beforeBody.length||this.body.length||this.afterBody.length||this.footer.length;e.enabled&&a&&(t.save(),t.globalAlpha=i,this.drawBackground(n,t,s,e),Ai(t,e.textDirection),n.y+=o.top,this.drawTitle(n,t,e),this.drawBody(n,t,e),this.drawFooter(n,t,e),Ti(t,e.textDirection),t.restore())}getActiveElements(){return this._active||[]}setActiveElements(t,e){const i=this._active,s=t.map((({datasetIndex:t,index:e})=>{const i=this.chart.getDatasetMeta(t);if(!i)throw new Error("Cannot find a dataset at index "+t);return{datasetIndex:t,element:i.data[e],index:e}})),n=!f(i,s),o=this._positionChanged(s,e);(n||o)&&(this._active=s,this._eventPosition=e,this._ignoreReplayEvents=!0,this.update(!0))}handleEvent(t,e,i=!0){if(e&&this._ignoreReplayEvents)return!1;this._ignoreReplayEvents=!1;const s=this.options,n=this._active||[],o=this._getActiveElements(t,n,e,i),a=this._positionChanged(o,t),r=e||!f(o,n)||a;return r&&(this._active=o,(s.enabled||s.external)&&(this._eventPosition={x:t.x,y:t.y},this.update(!0,e))),r}_getActiveElements(t,e,i,s){const n=this.options;if("mouseout"===t.type)return[];if(!s)return e.filter((t=>this.chart.data.datasets[t.datasetIndex]&&void 0!==this.chart.getDatasetMeta(t.datasetIndex).controller.getParsed(t.index)));const o=this.chart.getElementsAtEventForMode(t,n.mode,n,i);return n.reverse&&o.reverse(),o}_positionChanged(t,e){const{caretX:i,caretY:s,options:n}=this,o=Da[n.position].call(this,t,e);return!1!==o&&(i!==o.x||s!==o.y)}}var Na={id:"tooltip",_element:Wa,positioners:Da,afterInit(t,e,i){i&&(t.tooltip=new Wa({chart:t,options:i}))},beforeUpdate(t,e,i){t.tooltip&&t.tooltip.initialize(i)},reset(t,e,i){t.tooltip&&t.tooltip.initialize(i)},afterDraw(t){const e=t.tooltip;if(e&&e._willRender()){const i={tooltip:e};if(!1===t.notifyPlugins("beforeTooltipDraw",{...i,cancelable:!0}))return;e.draw(t.ctx),t.notifyPlugins("afterTooltipDraw",i)}},afterEvent(t,e){if(t.tooltip){const i=e.replay;t.tooltip.handleEvent(e.event,i,e.inChartArea)&&(e.changed=!0)}},defaults:{enabled:!0,external:null,position:"average",backgroundColor:"rgba(0,0,0,0.8)",titleColor:"#fff",titleFont:{weight:"bold"},titleSpacing:2,titleMarginBottom:6,titleAlign:"left",bodyColor:"#fff",bodySpacing:2,bodyFont:{},bodyAlign:"left",footerColor:"#fff",footerSpacing:2,footerMarginTop:6,footerFont:{weight:"bold"},footerAlign:"left",padding:6,caretPadding:2,caretSize:5,cornerRadius:6,boxHeight:(t,e)=>e.bodyFont.size,boxWidth:(t,e)=>e.bodyFont.size,multiKeyBackground:"#fff",displayColors:!0,boxPadding:0,borderColor:"rgba(0,0,0,0)",borderWidth:0,animation:{duration:400,easing:"easeOutQuart"},animations:{numbers:{type:"number",properties:["x","y","width","height","caretX","caretY"]},opacity:{easing:"linear",duration:200}},callbacks:Va},defaultRoutes:{bodyFont:"font",footerFont:"font",titleFont:"font"},descriptors:{_scriptable:t=>"filter"!==t&&"itemSort"!==t&&"external"!==t,_indexable:!1,callbacks:{_scriptable:!1,_indexable:!1},animation:{_fallback:!1},animations:{_fallback:"animation"}},additionalOptionScopes:["interaction"]};return Tn.register(Un,$o,go,t),Tn.helpers={...Hi},Tn._adapters=In,Tn.Animation=As,Tn.Animations=Ts,Tn.animator=bt,Tn.controllers=nn.controllers.items,Tn.DatasetController=js,Tn.Element=$s,Tn.elements=go,Tn.Interaction=Ki,Tn.layouts=ls,Tn.platforms=Ds,Tn.Scale=tn,Tn.Ticks=ae,Object.assign(Tn,Un,$o,go,t,Ds),Tn.Chart=Tn,"undefined"!=typeof window&&(window.Chart=Tn),Tn}));


/*!
  * CoreUI v4.2.0 (https://coreui.io)
  * Copyright 2025 [object Object]
  * Licensed under MIT (https://github.com/coreui/coreui-chartjs/blob/main/LICENSE)
  */
(function (global, factory) {
  typeof exports === 'object' && typeof module !== 'undefined' ? module.exports = factory() :
  typeof define === 'function' && define.amd ? define(factory) :
  (global = typeof globalThis !== 'undefined' ? globalThis : global || self, (global.coreui = global.coreui || {}, global.coreui.ChartJS = factory()));
})(this, (function () { 'use strict';

  /**
   * --------------------------------------------------------------------------
   * Custom Tooltips for Chart.js (v4.2.0): custom-tooltips.js
   * Licensed under MIT (https://github.com/coreui/coreui-chartjs/blob/main/LICENSE)
   * --------------------------------------------------------------------------
   */

  const ClassName = {
    TOOLTIP: 'chartjs-tooltip',
    TOOLTIP_BODY: 'chartjs-tooltip-body',
    TOOLTIP_BODY_ITEM: 'chartjs-tooltip-body-item',
    TOOLTIP_HEADER: 'chartjs-tooltip-header',
    TOOLTIP_HEADER_ITEM: 'chartjs-tooltip-header-item'
  };
  const getOrCreateTooltip = chart => {
    let tooltipEl = chart.canvas.parentNode.querySelector('div');
    if (!tooltipEl) {
      tooltipEl = document.createElement('div');
      tooltipEl.classList.add(ClassName.TOOLTIP);
      const table = document.createElement('table');
      table.style.margin = '0px';
      tooltipEl.append(table);
      chart.canvas.parentNode.append(tooltipEl);
    }
    return tooltipEl;
  };
  const customTooltips = context => {
    // Tooltip Element
    const {
      chart,
      tooltip
    } = context;
    const tooltipEl = getOrCreateTooltip(chart);

    // Hide if no tooltip
    if (tooltip.opacity === 0) {
      tooltipEl.style.opacity = 0;
      return;
    }

    // Set Text
    if (tooltip.body) {
      const titleLines = tooltip.title || [];
      const bodyLines = tooltip.body.map(b => b.lines);
      const tableHead = document.createElement('thead');
      tableHead.classList.add(ClassName.TOOLTIP_HEADER);
      for (const title of titleLines) {
        const tr = document.createElement('tr');
        tr.style.borderWidth = 0;
        tr.classList.add(ClassName.TOOLTIP_HEADER_ITEM);
        const th = document.createElement('th');
        th.style.borderWidth = 0;
        const text = document.createTextNode(title);
        th.append(text);
        tr.append(th);
        tableHead.append(tr);
      }
      const tableBody = document.createElement('tbody');
      tableBody.classList.add(ClassName.TOOLTIP_BODY);
      for (const [i, body] of bodyLines.entries()) {
        const colors = tooltip.labelColors[i];
        const span = document.createElement('span');
        span.style.background = colors.backgroundColor;
        span.style.borderColor = colors.borderColor;
        span.style.borderWidth = '2px';
        span.style.marginRight = '10px';
        span.style.height = '10px';
        span.style.width = '10px';
        span.style.display = 'inline-block';
        const tr = document.createElement('tr');
        tr.classList.add(ClassName.TOOLTIP_BODY_ITEM);
        const td = document.createElement('td');
        td.style.borderWidth = 0;
        const text = document.createTextNode(body);
        td.append(span);
        td.append(text);
        tr.append(td);
        tableBody.append(tr);
      }
      const tableRoot = tooltipEl.querySelector('table');

      // Remove old children
      while (tableRoot.firstChild) {
        tableRoot.firstChild.remove();
      }

      // Add new children
      tableRoot.append(tableHead);
      tableRoot.append(tableBody);
    }
    const {
      offsetLeft: positionX,
      offsetTop: positionY
    } = chart.canvas;

    // Display, position, and set styles for font
    tooltipEl.style.opacity = 1;
    tooltipEl.style.left = `${positionX + tooltip.caretX}px`;
    tooltipEl.style.top = `${positionY + tooltip.caretY}px`;
    tooltipEl.style.font = tooltip.options.bodyFont.string;
    tooltipEl.style.padding = `${tooltip.padding}px ${tooltip.padding}px`;
  };

  /**
   * --------------------------------------------------------------------------
   * Custom Tooltips for Chart.js (v4.1.0): index.umd.js
   * Licensed under MIT (https://github.com/coreui/coreui-chartjs/blob/main/LICENSE)
   * --------------------------------------------------------------------------
   */

  const index_umd = {
    customTooltips
  };

  return index_umd;

}));


(function (global, factory) {
    typeof exports === 'object' && typeof module !== 'undefined' ? factory(exports) :
    typeof define === 'function' && define.amd ? define(['exports'], factory) :
    (global = typeof globalThis !== 'undefined' ? globalThis : global || self, factory((global.coreui = global.coreui || {}, global.coreui.Utils = {})));
})(this, (function (exports) { 'use strict';

    /**
     * --------------------------------------------------------------------------
     * CoreUI Utils (v2.0.1): deepObjectsMerge.ts
     * Licensed under MIT (https://github.com/coreui/coreui-utils/blob/main/LICENSE)
     * --------------------------------------------------------------------------
     */
    var deepObjectsMerge = function (target, source) {
        // Iterate through `source` properties and if an `Object` set property to merge of `target` and `source` properties
        for (var _i = 0, _a = Object.keys(source); _i < _a.length; _i++) {
            var key = _a[_i];
            if (source[key] instanceof Object) {
                Object.assign(source[key], deepObjectsMerge(target[key], source[key]));
            }
        }
        // Join `target` and modified `source`
        Object.assign(target || {}, source);
        return target;
    };

    /**
     * --------------------------------------------------------------------------
     * CoreUI Utils (v2.0.1): getStyle.ts
     * Licensed under MIT (https://github.com/coreui/coreui-utils/blob/main/LICENSE)
     * --------------------------------------------------------------------------
     */
    var getStyle = function (property, element) {
        if (typeof window === 'undefined') {
            return;
        }
        if (typeof document === 'undefined') {
            return;
        }
        var _element = element !== null && element !== void 0 ? element : document.body;
        return window.getComputedStyle(_element, null).getPropertyValue(property).replace(/^\s/, '');
    };

    /**
     * --------------------------------------------------------------------------
     * CoreUI Utils (v2.0.1): getColor.ts
     * Licensed under MIT (https://github.com/coreui/coreui-utils/blob/main/LICENSE)
     * --------------------------------------------------------------------------
     */
    var getColor = function (rawProperty, element) {
        if (element === void 0) { element = document.body; }
        var property = "--".concat(rawProperty);
        var style = getStyle(property, element);
        return style ? style : rawProperty;
    };

    /**
     * --------------------------------------------------------------------------
     * CoreUI Utils (v2.0.1): hexToRgb.ts
     * Licensed under MIT (https://github.com/coreui/coreui-utils/blob/main/LICENSE)
     * --------------------------------------------------------------------------
     */
    /* eslint-disable no-magic-numbers */
    var hexToRgb = function (color) {
        if (typeof color === 'undefined') {
            throw new TypeError('Hex color is not defined');
        }
        color.match(/^#(?:[0-9a-f]{3}){1,2}$/i);
        var r;
        var g;
        var b;
        if (color.length === 7) {
            r = parseInt(color.slice(1, 3), 16);
            g = parseInt(color.slice(3, 5), 16);
            b = parseInt(color.slice(5, 7), 16);
        }
        else {
            r = parseInt(color.slice(1, 2), 16);
            g = parseInt(color.slice(2, 3), 16);
            b = parseInt(color.slice(3, 5), 16);
        }
        return "rgba(".concat(r, ", ").concat(g, ", ").concat(b, ")");
    };

    /**
     * --------------------------------------------------------------------------
     * CoreUI Utils (v2.0.1): hexToRgba.ts
     * Licensed under MIT (https://github.com/coreui/coreui-utils/blob/main/LICENSE)
     * --------------------------------------------------------------------------
     */
    /* eslint-disable no-magic-numbers */
    var hexToRgba = function (color, opacity) {
        if (opacity === void 0) { opacity = 100; }
        if (typeof color === 'undefined') {
            throw new TypeError('Hex color is not defined');
        }
        var hex = color.match(/^#(?:[0-9a-f]{3}){1,2}$/i);
        if (!hex) {
            throw new Error("".concat(color, " is not a valid hex color"));
        }
        var r;
        var g;
        var b;
        if (color.length === 7) {
            r = parseInt(color.slice(1, 3), 16);
            g = parseInt(color.slice(3, 5), 16);
            b = parseInt(color.slice(5, 7), 16);
        }
        else {
            r = parseInt(color.slice(1, 2), 16);
            g = parseInt(color.slice(2, 3), 16);
            b = parseInt(color.slice(3, 5), 16);
        }
        return "rgba(".concat(r, ", ").concat(g, ", ").concat(b, ", ").concat(opacity / 100, ")");
    };

    /**
     * --------------------------------------------------------------------------
     * CoreUI Utils (v2.0.1): makeUid.ts
     * Licensed under MIT (https://github.com/coreui/coreui-utils/blob/main/LICENSE)
     * --------------------------------------------------------------------------
     */
    //function for UI releted ID assignment, due to one in 10^15 probability of duplication
    var makeUid = function () {
        var key = Math.random().toString(36).substr(2);
        return 'uid-' + key;
    };

    /**
     * --------------------------------------------------------------------------
     * CoreUI Utils (v2.0.1): omitByKeys.ts
     * Licensed under MIT (https://github.com/coreui/coreui-utils/blob/main/LICENSE)
     * --------------------------------------------------------------------------
     */
    var omitByKeys = function (originalObject, keys) {
        var newObj = {};
        var objKeys = Object.keys(originalObject);
        for (var i = 0; i < objKeys.length; i++) {
            !keys.includes(objKeys[i]) && (newObj[objKeys[i]] = originalObject[objKeys[i]]);
        }
        return newObj;
    };

    /**
     * --------------------------------------------------------------------------
     * CoreUI Utils (v2.0.1): pickByKeys.ts
     * Licensed under MIT (https://github.com/coreui/coreui-utils/blob/main/LICENSE)
     * --------------------------------------------------------------------------
     */
    var pickByKeys = function (originalObject, keys) {
        var newObj = {};
        for (var i = 0; i < keys.length; i++) {
            newObj[keys[i]] = originalObject[keys[i]];
        }
        return newObj;
    };

    /**
     * --------------------------------------------------------------------------
     * CoreUI Utils (v2.0.1): rgbToHex.ts
     * Licensed under MIT (https://github.com/coreui/coreui-utils/blob/main/LICENSE)
     * --------------------------------------------------------------------------
     */
    var rgbToHex = function (color) {
        if (typeof color === 'undefined') {
            throw new TypeError('Hex color is not defined');
        }
        if (color === 'transparent') {
            return '#00000000';
        }
        var rgb = color.match(/^rgba?[\s+]?\([\s+]?(\d+)[\s+]?,[\s+]?(\d+)[\s+]?,[\s+]?(\d+)[\s+]?/i);
        if (!rgb) {
            throw new Error("".concat(color, " is not a valid rgb color"));
        }
        var r = "0".concat(parseInt(rgb[1], 10).toString(16));
        var g = "0".concat(parseInt(rgb[2], 10).toString(16));
        var b = "0".concat(parseInt(rgb[3], 10).toString(16));
        return "#".concat(r.slice(-2)).concat(g.slice(-2)).concat(b.slice(-2));
    };

    exports.deepObjectsMerge = deepObjectsMerge;
    exports.getColor = getColor;
    exports.getStyle = getStyle;
    exports.hexToRgb = hexToRgb;
    exports.hexToRgba = hexToRgba;
    exports.makeUid = makeUid;
    exports.omitByKeys = omitByKeys;
    exports.pickByKeys = pickByKeys;
    exports.rgbToHex = rgbToHex;

}));



var TomBG = {
  // Theme configuration is now loaded from the server via window.TomBGThemes
  themes: window.TomBGThemes || {},

  // Current picker target: 'background' or 'accent'
  pickerTarget: 'background',

  init: function () {
    // 1. Check for forced mode (Login Page) before looking at localStorage
    var saved = localStorage.getItem("tom-labs-bg-mode") || "ninja";
    var modeToUse = window.FORCED_BG_MODE || saved;

    this.apply(modeToUse);

    // Watch for theme changes (Light/Dark mode toggle) to update colors instantly
    var _this = this;
    var observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        if (mutation.attributeName === "data-coreui-theme") {
          var currentMode =
            window.FORCED_BG_MODE ||
            localStorage.getItem("tom-labs-bg-mode") ||
            "ninja";
          _this.apply(currentMode);
        }
      });
    });

    observer.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ["data-coreui-theme"]
    });

    this.updateCustomSlotsUI();
    this.initAccent();
    this.initCustomPicker();
    this.initWheel();
    this.initScenery();

    // Attach click handlers to preview spheres in plainColorModal
    var bgSphere = document.getElementById('designer-sphere-bg');
    var accentSphere = document.getElementById('designer-sphere-accent');
    var previewSphere = document.getElementById('designer-sphere-preview');
    
    if (bgSphere) {
      bgSphere.style.cursor = 'pointer';
      bgSphere.addEventListener('click', function() { TomBG.switchPickerTarget('background'); });
    }
    if (accentSphere) {
      accentSphere.style.cursor = 'pointer';
      accentSphere.addEventListener('click', function() { TomBG.switchPickerTarget('accent'); });
    }
    if (previewSphere) {
      previewSphere.style.cursor = 'pointer';
      previewSphere.addEventListener('click', function() { 
        TomBG.switchPickerTarget(TomBG.pickerTarget === 'background' ? 'accent' : 'background'); 
      });
    }

    // Handle editing state when modal opens/closes
    var modalEl = document.getElementById("plainColorModal");
    if (modalEl) {
      modalEl.addEventListener("show.bs.modal", function () {
        // Default to Slot 0 if no specific slot was picked via the wand icon
        if (_this.currentEditingSlot === null) _this.currentEditingSlot = 0;
      });
      modalEl.addEventListener("hidden.bs.modal", function () {
        _this.currentEditingSlot = null;
      });
    }

    // Restore theme state if dropdown is closed without selecting
    var themeToggle = document.querySelector('.theme-selector-btn');
    if (themeToggle) {
      themeToggle.addEventListener('hidden.coreui.dropdown', function () {
        if (window.TomVisuals) window.TomVisuals.syncUI();
      });
    }
  },

  currentEditingSlot: null,

  // ========================================================================
  // Accent Color System
  // ========================================================================
  initAccent: function () {
    var savedAccent = localStorage.getItem('tom-labs-accent-color');
    if (!savedAccent) {
      // Default accent if none saved
      savedAccent = '#8b91f9';
      localStorage.setItem('tom-labs-accent-color', savedAccent);
    }
    this.updateDesignerPreviews();
    this.updateContrastScores();
  },

  switchPickerTarget: function (target) {
    this.pickerTarget = target;
    var bgBtn = document.getElementById('picker-target-bg');
    var accentBtn = document.getElementById('picker-target-accent');
    if (bgBtn) bgBtn.classList.toggle('active', target === 'background');
    if (accentBtn) accentBtn.classList.toggle('active', target === 'accent');

    // Sync pickers to the currently-editing color
    var color = target === 'accent'
      ? (localStorage.getItem('tom-labs-accent-color') || '#8b91f9')
      : (localStorage.getItem('tom-labs-plain-color') || '#010d12');
    this.syncPickers(color);

    var preview = document.getElementById('hex-preview');
    if (preview) {
      preview.innerText = color.toUpperCase();
      preview.style.color = color;
    }
  },

  setAccentColor: function (hex) {
    hex = this.toHex(hex);
    localStorage.setItem('tom-labs-accent-color', hex);

    // Apply accent as --cui-primary immediately
    var pRGB = this.hexToRgbValues(hex);
    var themeStyle = document.getElementById('tom-theme-vars');
    if (themeStyle) {
      var current = themeStyle.innerHTML;
      // Replace or append accent vars
      current = current.replace(/--cui-primary:\s*[^;]+!important;/g, '--cui-primary: ' + hex + ' !important;');
      current = current.replace(/--cui-primary-rgb:\s*[^;]+!important;/g, '--cui-primary-rgb: ' + pRGB + ' !important;');
      themeStyle.innerHTML = current;
    }

    this.updateDesignerPreviews();
    this.updateContrastScores();
    this.syncToServer();
  },

  // WCAG contrast ratio calculation
  getRelativeLuminance: function (hex) {
    var rgb = hex.match(/[A-Za-z0-9]{2}/g).map(function (x) {
      var c = parseInt(x, 16) / 255;
      return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2];
  },

  getContrastRatio: function (hex1, hex2) {
    var l1 = this.getRelativeLuminance(hex1);
    var l2 = this.getRelativeLuminance(hex2);
    var lighter = Math.max(l1, l2);
    var darker = Math.min(l1, l2);
    return (lighter + 0.05) / (darker + 0.05);
  },

  updateContrastScores: function () {
    var bg = localStorage.getItem('tom-labs-plain-color') || '#010d12';
    var accent = localStorage.getItem('tom-labs-accent-color') || '#8b91f9';

    var bgLum = this.getRelativeLuminance(bg);
    var isDarkBg = bgLum < 0.5;

    var textRatio = isDarkBg ? this.getContrastRatio(bg, '#ffffff') : this.getContrastRatio(bg, '#000000');
    var accentRatio = this.getContrastRatio(bg, accent);

    var scores = [
      { id: 'contrast-text', ratio: textRatio, label: isDarkBg ? 'White on bg' : 'Black on bg' },
      { id: 'contrast-accent', ratio: accentRatio, label: 'Accent on bg' }
    ];

    scores.forEach(function (s) {
      var valEl = document.getElementById(s.id + '-val');
      var barEl = document.getElementById(s.id + '-bar');
      var badgeEl = document.getElementById(s.id + '-badge');
      var labelEl = document.getElementById(s.id + '-label');
      
      if (labelEl) labelEl.textContent = s.label;
      if (!valEl) return;

      var ratio = Math.round(s.ratio * 10) / 10;
      valEl.textContent = ratio.toFixed(1);

      var pct = Math.min(100, (ratio / 21) * 100);
      if (barEl) barEl.style.width = pct + '%';

      if (badgeEl) {
        if (ratio >= 7) {
          badgeEl.textContent = 'AAA';
          badgeEl.className = 'contrast-badge badge-aaa';
        } else if (ratio >= 4.5) {
          badgeEl.textContent = 'AA';
          badgeEl.className = 'contrast-badge badge-aa';
        } else {
          badgeEl.textContent = 'Fail';
          badgeEl.className = 'contrast-badge badge-fail';
        }
      }
    });

    // Update overall score header (uses the weakest link)
    var minRatio = Math.min(textRatio, accentRatio);
    var overallScore = document.getElementById('contrast-overall-score');
    var overallLabel = document.getElementById('contrast-overall-label');
    var stars = document.getElementById('contrast-stars');

    if (overallScore && overallLabel && stars) {
      overallScore.textContent = minRatio.toFixed(1);
      
      var starCount = 1;
      if (minRatio >= 7) {
        overallLabel.textContent = 'Excellent';
        overallLabel.className = 'fw-semibold text-success';
        starCount = 5;
      } else if (minRatio >= 4.5) {
        overallLabel.textContent = 'Good';
        overallLabel.className = 'fw-semibold text-success';
        starCount = 4;
      } else if (minRatio >= 3.0) {
        overallLabel.textContent = 'Poor';
        overallLabel.className = 'fw-semibold text-warning';
        starCount = 2;
      } else {
        overallLabel.textContent = 'Terrible';
        overallLabel.className = 'fw-semibold text-danger';
        starCount = 1;
      }

      var starHtml = '';
      for (var i = 0; i < 5; i++) {
        if (i < starCount) {
          starHtml += `<i class='bx bxs-star' style="font-size: 0.7rem; color: #fbbf24;"></i>`;
        } else {
          starHtml += `<i class='bx bx-star' style="font-size: 0.7rem; color: rgba(255,255,255,0.2);"></i>`;
        }
      }
      stars.innerHTML = starHtml;
    }
  },

  enhanceAccent: function () {
    var bg = localStorage.getItem('tom-labs-plain-color') || '#010d12';
    var accent = localStorage.getItem('tom-labs-accent-color') || '#8b91f9';
    var ratio = this.getContrastRatio(bg, accent);

    if (ratio >= 4.5) {
      if (typeof TomNotify !== 'undefined') {
        TomNotify.show('Accent already passes AA contrast!', 'Enhance', 'success', 3000);
      }
      return;
    }

    // Determine if we need to lighten or darken accent
    var bgLum = this.getRelativeLuminance(bg);
    var step = bgLum > 0.5 ? -5 : 5;
    var enhanced = accent;
    var maxIter = 60;

    for (var i = 0; i < maxIter; i++) {
      enhanced = this.adjustColor(enhanced, step);
      var newRatio = this.getContrastRatio(bg, enhanced);
      if (newRatio >= 4.5) break;
    }

    this.setAccentColor(enhanced);
    this.syncPickers(enhanced);
    this.updateContrastScores(bg, enhanced);

    var preview = document.getElementById('hex-preview');
    if (preview && this.pickerTarget === 'accent') {
      preview.innerText = enhanced.toUpperCase();
      preview.style.color = enhanced;
    }

    if (typeof TomNotify !== 'undefined') {
      var finalRatio = this.getContrastRatio(bg, enhanced);
      TomNotify.show('Accent enhanced to ' + (Math.round(finalRatio * 10) / 10) + ':1 contrast ratio', 'Enhance', 'success', 3000);
    }
  },

  updateDesignerPreviews: function () {
    var bg = localStorage.getItem('tom-labs-plain-color') || '#010d12';
    var accent = localStorage.getItem('tom-labs-accent-color') || '#8b91f9';

    // Background sphere
    var bgSphere = document.getElementById('designer-sphere-bg');
    if (bgSphere) bgSphere.style.background = 'radial-gradient(circle at 35% 30%, ' + this.adjustColor(bg, 25) + ', ' + bg + ' 70%)';

    // Accent sphere
    var accentSphere = document.getElementById('designer-sphere-accent');
    if (accentSphere) accentSphere.style.background = 'radial-gradient(circle at 35% 30%, ' + this.adjustColor(accent, 25) + ', ' + accent + ' 70%)';

    // Preview combined sphere
    var previewSphere = document.getElementById('designer-sphere-preview');
    var previewDot = document.getElementById('designer-sphere-preview-dot');
    if (previewSphere) previewSphere.style.background = 'radial-gradient(circle at 35% 30%, ' + this.adjustColor(bg, 20) + ', ' + bg + ' 70%)';
    if (previewDot) previewDot.style.background = accent;
  },

  // Save custom theme with both bg + accent
  saveCustomTheme: function (index) {
    var bg = localStorage.getItem('tom-labs-plain-color') || '#010d12';
    var accent = localStorage.getItem('tom-labs-accent-color') || '#8b91f9';
    var theme = JSON.stringify({ bg: bg, accent: accent });
    localStorage.setItem('tom-labs-custom-theme-' + index, theme);
    // Also keep legacy single-color for backward compat
    localStorage.setItem('tom-labs-custom-color-' + index, bg);
    this.updateCustomSlotsUI();
    this.syncToServer();

    if (typeof TomNotify !== 'undefined') {
      TomNotify.show('Custom theme saved to slot ' + (index + 1), 'Saved', 'success', 3000);
    }
  },

  applyCustomTheme: function (index) {
    var themeStr = localStorage.getItem('tom-labs-custom-theme-' + index);
    if (themeStr) {
      try {
        var theme = JSON.parse(themeStr);
        this.setPlainColor(theme.bg);
        this.setAccentColor(theme.accent);
        this.setMode('plain');
        return;
      } catch (e) {}
    }
    // Fallback to legacy single color
    var slotColor = localStorage.getItem('tom-labs-custom-color-' + index);
    if (slotColor) {
      this.setPlainColor(slotColor);
      this.setMode('plain');
    }
  },

  deleteCustomTheme: function (index) {
    // We want to delete the slot at `index`, but since custom slots are populated 0..N,
    // we should shift the higher index themes down by 1 so there are no empty gaps.
    var maxSlots = 10;
    for (var i = index; i < maxSlots - 1; i++) {
      var nextTheme = localStorage.getItem('tom-labs-custom-theme-' + (i + 1));
      var nextColor = localStorage.getItem('tom-labs-custom-color-' + (i + 1));
      
      if (nextTheme) {
        localStorage.setItem('tom-labs-custom-theme-' + i, nextTheme);
      } else {
        localStorage.removeItem('tom-labs-custom-theme-' + i);
      }
      
      if (nextColor) {
        localStorage.setItem('tom-labs-custom-color-' + i, nextColor);
      } else {
        localStorage.removeItem('tom-labs-custom-color-' + i);
      }
    }
    
    // Clear the very last slot
    localStorage.removeItem('tom-labs-custom-theme-' + (maxSlots - 1));
    localStorage.removeItem('tom-labs-custom-color-' + (maxSlots - 1));
    
    this.updateCustomSlotsUI();
    this.syncToServer();
    
    if (typeof TomNotify !== 'undefined') {
      TomNotify.show('Custom theme deleted', 'Deleted', 'warning', 3000);
    }
  },

  openDesignerForSlot: function (index) {
    this.currentEditingSlot = index;
    // Load existing theme data
    var themeStr = localStorage.getItem('tom-labs-custom-theme-' + index);
    if (themeStr) {
      try {
        var theme = JSON.parse(themeStr);
        this.setPlainColor(theme.bg);
        this.setAccentColor(theme.accent);
      } catch (e) {
        var slotColor = localStorage.getItem('tom-labs-custom-color-' + index) || '#ffffff';
        this.setPlainColor(slotColor);
      }
    } else {
      var slotColor = localStorage.getItem('tom-labs-custom-color-' + index) || '#ffffff';
      this.setPlainColor(slotColor);
    }
    this.switchPickerTarget('background');
    var mEl = document.getElementById('plainColorModal');
    var m = coreui.Modal.getInstance(mEl) || new coreui.Modal(mEl);
    m.show();
  },

  applySlot: function (index) {
    var themeStr = localStorage.getItem('tom-labs-custom-theme-' + index);
    if (themeStr) {
      this.applyCustomTheme(index);
    } else {
      var slotColor = localStorage.getItem('tom-labs-custom-color-' + index);
      if (slotColor) {
        this.setPlainColor(slotColor);
      } else {
        this.openDesignerForSlot(index);
      }
    }
  },

  initWheel: function () {
    var wheel = document.getElementById("color-wheel");
    if (!wheel) return;

    var isDragging = false;

    var updateFromWheel = function (e) {
      var rect = wheel.getBoundingClientRect();
      var centerX = rect.width / 2;
      var centerY = rect.height / 2;
      var clientX = e.clientX || (e.touches && e.touches[0].clientX);
      var clientY = e.clientY || (e.touches && e.touches[0].clientY);
      var x = clientX - rect.left - centerX;
      var y = clientY - rect.top - centerY;

      // Calculate Angle (Hue)
      var angle = Math.atan2(y, x) * (180 / Math.PI) + 90;
      if (angle < 0) angle += 360;

      // Calculate Distance (Saturation)
      var dist = Math.sqrt(x * x + y * y);
      var radius = rect.width / 2;
      var s = Math.min(100, (dist / radius) * 100);
      var v = document.getElementById("brightness-slider") ? document.getElementById("brightness-slider").value : 100;

      var hex = TomBG.hsvToHex(angle, s, v);
      TomBG.setPlainColor(hex);

      // Update wheel cursor position
      var cursor = document.getElementById("wheel-cursor");
      if (cursor) {
        cursor.style.left = (centerX + x) + "px";
        cursor.style.top = (centerY + y) + "px";
      }
    };

    wheel.addEventListener("mousedown", function (e) {
      isDragging = true;
      updateFromWheel(e);
    });

    window.addEventListener("mousemove", function (e) {
      if (isDragging) updateFromWheel(e);
    });

    window.addEventListener("mouseup", function () {
      isDragging = false;
    });
  },

  updateBrightness: function (val) {
    var valBright = document.getElementById("val-bright");
    if (valBright) valBright.innerText = val + "%";
    var currentHex = localStorage.getItem("tom-labs-plain-color") || "#010d12";
    var hsv = this.hexToHsv(currentHex);
    var newHex = this.hsvToHex(hsv.h, hsv.s, val);
    this.setPlainColor(newHex);
  },

  hexToHsv: function (hex) {
    var rgb = hex.match(/[A-Za-z0-9]{2}/g).map(function (x) { return parseInt(x, 16) / 255; });
    var max = Math.max.apply(Math, rgb), min = Math.min.apply(Math, rgb);
    var d = max - min;
    var h, s = max === 0 ? 0 : d / max, v = max;
    if (max === min) h = 0;
    else {
      switch (max) {
        case rgb[0]: h = (rgb[1] - rgb[2]) / d + (rgb[1] < rgb[2] ? 6 : 0); break;
        case rgb[1]: h = (rgb[2] - rgb[0]) / d + 2; break;
        case rgb[2]: h = (rgb[0] - rgb[1]) / d + 4; break;
      }
      h /= 6;
    }
    return { h: h * 360, s: s * 100, v: v * 100 };
  },

  initCustomPicker: function () {
    var specMap = document.getElementById("spectrum-map");
    if (!specMap) return;

    var isDragging = false;

    var updateFromMap = function (e) {
      var rect = specMap.getBoundingClientRect();
      var clientX = e.clientX || (e.touches && e.touches[0].clientX);
      var clientY = e.clientY || (e.touches && e.touches[0].clientY);
      var x = Math.max(0, Math.min(rect.width, clientX - rect.left));
      var y = Math.max(0, Math.min(rect.height, clientY - rect.top));

      var h = (x / rect.width) * 360;
      var s, v;

      if (y < rect.height / 2) {
        s = (y / (rect.height / 2)) * 100;
        v = 100;
      } else {
        s = 100;
        v = 100 - ((y - rect.height / 2) / (rect.height / 2)) * 100;
      }

      var hex = TomBG.hsvToHex(h, s, v);
      TomBG.setPlainColor(hex);

      var cursor = document.getElementById("spectrum-cursor");
      if (cursor) {
        cursor.style.left = x + "px";
        cursor.style.top = y + "px";
      }
    };

    specMap.addEventListener("mousedown", function (e) {
      isDragging = true;
      updateFromMap(e);
    });

    window.addEventListener("mousemove", function (e) {
      if (isDragging) updateFromMap(e);
    });

    window.addEventListener("mouseup", function () {
      isDragging = false;
    });

    // Palettes
    document.querySelectorAll(".palette-item").forEach(function (p) {
      p.onclick = function () { TomBG.setPlainColor(p.style.backgroundColor); };
    });

    // Pencils
    document.querySelectorAll(".pencil-item").forEach(function (p) {
      p.onclick = function () { TomBG.setPlainColor(p.getAttribute("data-color")); };
    });
  },

  hsvToHex: function (h, s, v) {
    s /= 100;
    v /= 100;
    var i = Math.floor(h / 60);
    var f = h / 60 - i;
    var p = v * (1 - s);
    var q = v * (1 - f * s);
    var t = v * (1 - (1 - f) * s);
    var r, g, b;
    switch (i % 6) {
      case 0: r = v, g = t, b = p; break;
      case 1: r = q, g = v, b = p; break;
      case 2: r = p, g = v, b = t; break;
      case 3: r = p, g = q, b = v; break;
      case 4: r = t, g = p, b = v; break;
      case 5: r = v, g = p, b = q; break;
    }
    var toHex = function (x) {
      var hex = Math.round(x * 255).toString(16);
      return hex.length === 1 ? "0" + hex : hex;
    };
    return "#" + toHex(r) + toHex(g) + toHex(b);
  },

  switchPickerTab: function (tabId) {
    // Update buttons
    var tabs = document.querySelectorAll(".designer-tab");
    tabs.forEach(function (t) {
      var title = t.getAttribute("title").toLowerCase();
      if (title === tabId) {
        t.classList.add("active");
      } else {
        t.classList.remove("active");
      }
    });

    // Update content
    var modes = document.querySelectorAll(".picker-mode");
    modes.forEach(function (m) {
      m.classList.add("d-none");
      m.classList.remove("active");
    });

    var activeMode = document.getElementById("picker-" + tabId);
    if (activeMode) {
      activeMode.classList.remove("d-none");
      activeMode.classList.add("active");
    }

    // Toggle global brightness slider for visual modes
    var brightCont = document.getElementById("global-brightness-cont");
    if (brightCont) {
      if (tabId === "wheel" || tabId === "spectrum") {
        brightCont.classList.remove("d-none");
      } else {
        brightCont.classList.add("d-none");
      }
    }
  },

  updateFromSliders: function () {
    var r = document.querySelector(".rgb-slider.r").value;
    var g = document.querySelector(".rgb-slider.g").value;
    var b = document.querySelector(".rgb-slider.b").value;

    var valR = document.getElementById("val-r");
    var valG = document.getElementById("val-g");
    var valB = document.getElementById("val-b");
    if (valR) valR.innerText = r;
    if (valG) valG.innerText = g;
    if (valB) valB.innerText = b;

    var toHex = function (c) {
      var hex = parseInt(c).toString(16);
      return hex.length === 1 ? "0" + hex : hex;
    };
    var hex = "#" + toHex(r) + toHex(g) + toHex(b);
    this.setPlainColor(hex);
  },

  syncPickers: function (hex) {
    var hsv = this.hexToHsv(hex);
    var rgb = hex.match(/[A-Za-z0-9]{2}/g).map(function (v) { return parseInt(v, 16); });

    // 1. Sync Sliders
    var rSlider = document.querySelector(".rgb-slider.r");
    if (rSlider) {
      rSlider.value = rgb[0];
      document.querySelector(".rgb-slider.g").value = rgb[1];
      document.querySelector(".rgb-slider.b").value = rgb[2];
      var valR = document.getElementById("val-r");
      var valG = document.getElementById("val-g");
      var valB = document.getElementById("val-b");
      if (valR) valR.innerText = rgb[0];
      if (valG) valG.innerText = rgb[1];
      if (valB) valB.innerText = rgb[2];
    }

    // 2. Sync Spectrum Cursor
    var specMap = document.getElementById("spectrum-map");
    var specCursor = document.getElementById("spectrum-cursor");
    if (specMap && specCursor) {
      var rect = specMap.getBoundingClientRect();
      var x = (hsv.h / 360) * rect.width;
      var y;
      if (hsv.v === 100) {
        y = (hsv.s / 100) * (rect.height / 2);
      } else {
        y = rect.height / 2 + (1 - hsv.v / 100) * (rect.height / 2);
      }
      specCursor.style.left = x + "px";
      specCursor.style.top = y + "px";
    }

    // 3. Sync Wheel Cursor
    var wheel = document.getElementById("color-wheel");
    var wheelCursor = document.getElementById("wheel-cursor");
    if (wheel && wheelCursor) {
      var rect = wheel.getBoundingClientRect();
      var centerX = rect.width / 2;
      var centerY = rect.height / 2;
      var angle = (hsv.h - 90) * (Math.PI / 180);
      var radius = (hsv.s / 100) * (rect.width / 2);
      var x = centerX + radius * Math.cos(angle);
      var y = centerY + radius * Math.sin(angle);
      wheelCursor.style.left = x + "px";
      wheelCursor.style.top = y + "px";
    }

    // 4. Sync Brightness Slider
    var bSlider = document.getElementById("brightness-slider");
    if (bSlider) {
      bSlider.value = Math.round(hsv.v);
      var valBright = document.getElementById("val-bright");
      if (valBright) valBright.innerText = Math.round(hsv.v) + "%";
    }
  },

  setPlainColor: function (color) {
    var hex = this.toHex(color);

    // If pickerTarget is 'accent', route to accent color instead
    if (this.pickerTarget === 'accent') {
      this.setAccentColor(hex);
      // Sync preview
      var preview = document.getElementById('hex-preview');
      if (preview) {
        preview.innerText = hex.toUpperCase();
        preview.style.color = hex;
      }
      this.syncPickers(hex);
      return;
    }

    // Default to Slot 0 if no slot is active, ensuring the Designer always has a target
    var targetSlot = (this.currentEditingSlot !== null) ? this.currentEditingSlot : 0;

    // Save to the target slot (Real-time update)
    this.saveCustomColor(targetSlot, hex);

    // Always update main theme and apply immediately for real-time feedback
    localStorage.setItem("tom-labs-plain-color", hex);
    localStorage.setItem("tom-labs-bg-mode", "plain");
    this.apply("plain");
    this.syncToServer();

    // Sync preview
    var preview = document.getElementById("hex-preview");
    if (preview) {
      preview.innerText = hex.toUpperCase();
      preview.style.color = hex;
    }

    // Update active tab icon color ONLY
    var activeTab = document.querySelector(".designer-tab.active i");
    if (activeTab) {
      activeTab.style.color = hex;
    }

    // Reset other icons to default opacity
    document.querySelectorAll(".designer-tab:not(.active) i").forEach(function (i) { i.style.color = ""; });

    // Update designer previews + contrast
    this.updateDesignerPreviews();
    this.updateContrastScores();

    // Global Sync all other pickers
    this.syncPickers(hex);
  },

  apply: function (mode) {
    var scene = document.getElementById("scene");
    if (!scene) return;

    var root = document.documentElement;
    var isLight = root.getAttribute("data-coreui-theme") === "light";
    var sc = document.querySelector(".scenery-container");

    // Dynamically toggle bg-mode classes on the root HTML element
    var classesToRemove = [];
    for (var i = 0; i < root.classList.length; i++) {
      var cls = root.classList[i];
      if (cls && cls.indexOf("bg-mode-") === 0) {
        classesToRemove.push(cls);
      }
    }
    classesToRemove.forEach(function (c) { root.classList.remove(c); });
    root.classList.add("bg-mode-" + mode);

    var cssVars = "";
    var setVar = function (name, value) {
      cssVars += name + ": " + value + " !important; ";
    };

    if (mode === "plain") {
      var color = localStorage.getItem("tom-labs-plain-color") || "#0b1e36";
      if (sc) sc.style.display = "none";
      scene.style.display = "none";
      document.body.style.background = "";
      root.classList.add("mode-plain");

      var safeColor = isLight ? this.ensureLightness(color, 0.95) : this.ensureDarkness(color, 0.2);

      if (isLight) {
        var baseLight = this.ensureLightness(color, 0.98);
        setVar("--c1", "#ffffff");
        setVar("--c2", baseLight);
        setVar("--c3", this.adjustColor(baseLight, -2));
        setVar("--c4", this.adjustColor(baseLight, -5));
        setVar("--c5", "#ffffff");
        setVar("--c6", baseLight);
        setVar("--c7", this.adjustColor(baseLight, -3));
        setVar("--snow-color", "rgba(0,0,0,0.05)");
      } else {
        setVar("--c1", this.adjustColor(safeColor, -10));
        setVar("--c2", safeColor);
        setVar("--c3", this.adjustColor(safeColor, 8));
        setVar("--c4", this.adjustColor(safeColor, 15));
        setVar("--c5", this.adjustColor(safeColor, 5));
        setVar("--c6", safeColor);
        setVar("--c7", this.adjustColor(safeColor, -5));
        setVar("--snow-color", "#ffffff");
      }

      // Use stored accent color instead of auto-deriving from background
      var savedAccent = localStorage.getItem('tom-labs-accent-color');
      var primaryColor = savedAccent || this.adjustColor(color, isLight ? -40 : 40);
      var pRGB = this.hexToRgbValues(primaryColor);

      setVar("--glass-bg", isLight ? "#ffffff" : this.hexToRgba(safeColor, 0.88));
      setVar("--glass-bg-solid", isLight ? "#ffffff" : this.hexToRgba(safeColor, 0.96));
      setVar("--cui-card-bg", isLight ? "#ffffff" : this.hexToRgba(safeColor, 0.45));
      setVar("--cui-card-bg-solid", isLight ? "#ffffff" : this.hexToRgba(safeColor, 0.95));
      setVar("--cui-body-bg", safeColor);
      setVar("--cui-primary", primaryColor);
      setVar("--cui-primary-rgb", pRGB);
      setVar("--cui-sidebar-bg", isLight ? "#ffffff" : this.hexToRgba(safeColor, 0.97));
      setVar("--cui-header-bg", isLight ? "#ffffff" : this.hexToRgba(safeColor, 0.92));

      if (typeof TomParallax !== "undefined") TomParallax.destroy();
    } else {
      scene.style.display = "block";
      document.body.style.background = "transparent";
      if (sc) {
        sc.style.display = "block";
        sc.style.opacity = "0";
      }
      root.classList.remove("mode-plain");

      var theme = this.themes[mode] || this.themes["ninja"];
      var assets = theme.assets || theme;
      var themeColor = theme.color || "#0b1e36";

      if (themeColor) {
        var safeColor = isLight ? this.ensureLightness(themeColor, 0.8) : this.ensureDarkness(themeColor, 0.15);
        var primaryColor = theme.primary || this.adjustColor(themeColor, isLight ? -40 : 40);
        var pRGB = this.hexToRgbValues(primaryColor);

        setVar("--glass-bg", isLight ? "rgba(255, 255, 255, 0.79)" : this.hexToRgba(safeColor, 0.85));
        setVar("--glass-bg-solid", isLight ? this.hexToRgba(this.ensureLightness(themeColor, 0.92), 0.94) : this.hexToRgba(safeColor, 0.94));
        setVar("--cui-card-bg", isLight ? "rgba(255, 255, 255, 0.7)" : this.hexToRgba(safeColor, 0.2));
        setVar("--cui-card-bg-solid", isLight ? this.hexToRgba(this.ensureLightness(themeColor, 0.96), 0.94) : this.hexToRgba(safeColor, 0.95));
        setVar("--cui-body-bg", safeColor);
        setVar("--cui-primary", primaryColor);
        setVar("--cui-primary-rgb", pRGB);
        setVar("--cui-sidebar-bg", isLight ? "rgba(255, 255, 255, 0.6)" : this.hexToRgba(safeColor, 0.95));
        setVar("--cui-header-bg", isLight ? "rgba(255, 255, 255, 0.4)" : this.hexToRgba(safeColor, 0.85));

        setVar("--c1", isLight ? "#ffffff" : this.adjustColor(safeColor, -5));
        setVar("--c2", isLight ? "#f8f9fa" : safeColor);
        setVar("--c3", isLight ? "#ffffff" : this.adjustColor(safeColor, 5));
        setVar("--c4", isLight ? "#f0f2f5" : this.adjustColor(safeColor, 10));
      }

      var layers = scene.querySelectorAll(".bg-cover");
      layers.forEach(function (layer, index) {
        if (assets[index]) {
          layer.style.backgroundImage = "url('" + assets[index] + "')";
          layer.style.display = "block";
        } else {
          layer.style.backgroundImage = "none";
          layer.style.display = "none";
        }
      });

      if (typeof TomParallax !== "undefined") TomParallax.init();
    }

    // Inject variables into a clean style tag to keep HTML tag simple like SNA
    var themeStyle = document.getElementById("tom-theme-vars");
    if (!themeStyle) {
      themeStyle = document.createElement("style");
      themeStyle.id = "tom-theme-vars";
      document.head.appendChild(themeStyle);
    }
    themeStyle.innerHTML = `:root { ${cssVars} }`;

    // Remove all style attributes from root to keep it clean like SNA
    this.updateActiveSwatchUI();
    root.removeAttribute("style");
  },

  adjustColor: function (hex, percent) {
    var num = parseInt(hex.replace("#", ""), 16),
      amt = Math.round(2.55 * percent),
      R = (num >> 16) + amt,
      B = (num >> 8 & 0x00ff) + amt,
      G = (num & 0x0000ff) + amt;
    return (
      "#" +
      (
        0x1000000 +
        (R < 255 ? (R < 1 ? 0 : R) : 255) * 0x10000 +
        (B < 255 ? (B < 1 ? 0 : B) : 255) * 0x100 +
        (G < 255 ? (G < 1 ? 0 : G) : 255)
      )
        .toString(16)
        .slice(1)
    );
  },

  hexToRgba: function (hex, opacity) {
    var rgb = this.hexToRgbValues(hex);
    return "rgba(" + rgb + ", " + opacity + ")";
  },

  hexToRgbValues: function (hex) {
    var num = parseInt(hex.replace("#", ""), 16),
      R = (num >> 16) & 0xff,
      G = (num >> 8) & 0xff,
      B = num & 0xff;
    return R + ", " + G + ", " + B;
  },

  ensureDarkness: function (hex, maxLuminance) {
    var rgbVal = this.hexToRgbValues(hex);
    var rgb = rgbVal.split(",").map(Number);
    var luminance = (0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2]) / 255;

    if (luminance > maxLuminance) {
      var factor = maxLuminance / luminance;
      var r = Math.round(rgb[0] * factor);
      var g = Math.round(rgb[1] * factor);
      var b = Math.round(rgb[2] * factor);
      var toHexStr = function (c) {
        var h = c.toString(16);
        return h.length === 1 ? "0" + h : h;
      };
      return "#" + toHexStr(r) + toHexStr(g) + toHexStr(b);
    }
    return hex;
  },

  ensureLightness: function (hex, minLuminance) {
    var rgbVal = this.hexToRgbValues(hex);
    var rgb = rgbVal.split(",").map(Number);
    var luminance = (0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2]) / 255;

    if (luminance < minLuminance) {
      var factor = (1 - minLuminance) / (1 - luminance);
      var r = Math.round(255 - (255 - rgb[0]) * factor);
      var g = Math.round(255 - (255 - rgb[1]) * factor);
      var b = Math.round(255 - (255 - rgb[2]) * factor);
      var toHexStr = function (c) {
        var h = c.toString(16);
        return h.length === 1 ? "0" + h : h;
      };
      return "#" + toHexStr(r) + toHexStr(g) + toHexStr(b);
    }
    return hex;
  },

  extractColorFromImage: function (imageSrc, callback) {
    var img = new Image();
    img.crossOrigin = "anonymous";
    img.onload = function () {
      try {
        var canvas = document.createElement("canvas");
        var ctx = canvas.getContext("2d");
        var size = 50;
        canvas.width = size;
        canvas.height = size;
        ctx.drawImage(img, 0, 0, size, size);
        var data = ctx.getImageData(0, 0, size, size).data;
        var r = 0, g = 0, b = 0, count = 0;
        for (var i = 0; i < data.length; i += 16) {
          r += data[i];
          g += data[i + 1];
          b += data[i + 2];
          count++;
        }
        r = Math.round(r / count);
        g = Math.round(g / count);
        b = Math.round(b / count);
        var hex = "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
        callback(hex);
      } catch (e) {
        console.error("CORS or Canvas Error in color extraction:", e);
        callback("#010d12");
      }
    };
    img.onerror = function () {
      callback("#010d12");
    };
    img.src = imageSrc;
  },

  saveCustomColor: function (index, color) {
    var hex = this.toHex(color);
    localStorage.setItem("tom-labs-custom-color-" + index, hex);
    this.updateCustomSlotsUI();
    this.syncToServer();
  },

  setMode: function (mode) {
    // Auto-switch theme (light/dark) to match the visible background grid
    // This ensures that selecting a dark wallpaper also switches to dark theme and vice versa
    var visibleGrid = document.querySelector('.theme-bg-grid[style*="display: grid"], .theme-bg-grid:not(.d-none):not([style*="display: none"])');
    if (visibleGrid) {
      var gridTheme = visibleGrid.getAttribute('data-theme');
      var currentTheme = localStorage.getItem('tom-labs-theme') || 'dark';
      if (gridTheme && gridTheme !== currentTheme) {
        // Switch the full theme (colors, sidebar, header, icon) to match the grid
        if (typeof window.changeTheme === 'function') {
          window.changeTheme(gridTheme);
        }
      }
    }

    localStorage.setItem("tom-labs-bg-mode", mode);
    this.apply(mode);
    this.syncToServer();

    // Update thumbnails UI for the new Mega Dropdown
    document.querySelectorAll('.theme-bg-item').forEach(function (item) {
      item.classList.toggle('active', item.getAttribute('data-mode') === mode);
    });
  },

  updateCustomSlotsUI: function () {
    var container = document.getElementById('dynamic-custom-slots');
    if (!container) return;

    // Clear existing dynamically added slots
    var existingSlots = container.querySelectorAll('.dynamic-slot');
    existingSlots.forEach(function(slot) { slot.remove(); });

    var slotCount = 0;
    var maxSlots = 10;
    var html = '';

    for (var i = 0; i < maxSlots; i++) {
      var themeStr = localStorage.getItem('tom-labs-custom-theme-' + i);
      var colorStr = localStorage.getItem("tom-labs-custom-color-" + i);
      
      if (themeStr || colorStr) {
        slotCount++;
        var bg = "rgba(255,255,255,0.05)";
        var accent = "";
        var hasAccent = false;

        if (themeStr) {
          try {
            var theme = JSON.parse(themeStr);
            bg = theme.bg;
            accent = theme.accent;
            hasAccent = true;
          } catch(e){}
        } else if (colorStr) {
          bg = colorStr;
        }

        var gradient = 'radial-gradient(circle at 35% 30%, ' + this.adjustColor(bg, 25) + ', ' + bg + ' 70%)';

        html += `
          <div class="text-center dynamic-slot position-relative swatch-sphere-wrap swatch-item" style="width: 72px;" data-bg="${bg}" data-accent="${accent}" onmouseenter="this.querySelector('.action-btns').style.opacity='1'" onmouseleave="this.querySelector('.action-btns').style.opacity='0'">
              <div class="rounded-circle mx-auto mb-2 swatch-sphere dual-sphere d-flex align-items-center justify-content-center position-relative pointer" 
                   onclick="TomBG.applyCustomTheme(${i})"
                   style="width: 52px; height: 52px; border: 2px solid ${bg}; padding: 3px; background: transparent; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);">
                  <div class="w-100 h-100 rounded-circle position-relative" style="background: linear-gradient(to bottom, #1e293b 50%, #ffffff 50%); box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);">
                      ${hasAccent ? `<span class="dual-sphere-dot" style="background: ${accent}; border: 2px solid rgba(255,255,255,0.8); width: 14px; height: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.3); position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); border-radius: 50%;"></span>` : ''}
                  </div>
                  <div class="active-badge position-absolute shadow-sm" style="top: -4px; right: -4px; width: 16px; height: 16px; background: #22c55e; border-radius: 50%; color: white; display: none; align-items: center; justify-content: center; font-size: 11px; border: 2px solid var(--cui-body-bg); z-index: 2;">
                      <i class='bx bx-check fw-bold'></i>
                  </div>
              </div>
              <span class="d-block text-body-secondary" style="font-size: 0.65rem; font-weight: 500; line-height: 1.2;">Custom ${i + 1}</span>
              
              <div class="action-btns position-absolute w-100 d-flex justify-content-between" style="top: -5px; padding: 0 2px; opacity: 0; transition: opacity 0.2s ease; pointer-events: none; z-index: 5;">
                  <button class="btn btn-sm btn-success rounded-circle shadow-sm d-flex align-items-center justify-content-center p-0 action-btn" 
                          onclick="TomBG.openDesignerForSlot(${i}); event.stopPropagation();"
                          style="width: 20px; height: 20px; border: 1px solid rgba(255,255,255,0.5); pointer-events: auto; background-color: #22c55e;" title="Edit">
                      <i class='bx bxs-pencil' style="font-size: 10px; color: #fff;"></i>
                  </button>
                  <button class="btn btn-sm btn-danger rounded-circle shadow-sm d-flex align-items-center justify-content-center p-0 action-btn" 
                          onclick="TomBG.deleteCustomTheme(${i}); event.stopPropagation();"
                          style="width: 20px; height: 20px; border: 1px solid rgba(255,255,255,0.5); pointer-events: auto; background-color: #ef4444;" title="Delete">
                      <i class='bx bx-x' style="font-size: 14px; color: #fff;"></i>
                  </button>
              </div>
          </div>
        `;
      }
    }

    // Insert before the 'Create New' button
    var createNewBtn = container.querySelector('.create-new-slot');
    if (createNewBtn) {
      createNewBtn.insertAdjacentHTML('beforebegin', html);
      // Hide Create New button if we reached the max limit
      createNewBtn.style.display = slotCount >= maxSlots ? 'none' : 'block';
      
      // Update onclick to use the next available slot index
      var nextIndex = -1;
      for (var i = 0; i < maxSlots; i++) {
        if (!localStorage.getItem('tom-labs-custom-theme-' + i) && !localStorage.getItem('tom-labs-custom-color-' + i)) {
          nextIndex = i;
          break;
        }
      }
      
      if (nextIndex !== -1) {
        createNewBtn.setAttribute('onclick', "TomBG.openDesignerForSlot(" + nextIndex + "); var m = coreui.Modal.getInstance(document.getElementById('bgSelectModal')); if(m)m.hide();");
      }
    }
    
    this.updateActiveSwatchUI();
  },

  updateActiveSwatchUI: function () {
    var currentBg = localStorage.getItem('tom-labs-plain-color') || '#010d12';
    var currentAccent = localStorage.getItem('tom-labs-accent-color') || '#8b91f9';
    var mode = localStorage.getItem('tom-labs-bg-mode') || 'ninja';
    
    document.querySelectorAll('.swatch-item').forEach(function(item) {
      var badge = item.querySelector('.active-badge');
      if (!badge) return;
      
      var itemBg = item.getAttribute('data-bg');
      var itemAccent = item.getAttribute('data-accent');
      
      var bgMatches = itemBg && itemBg.toLowerCase() === currentBg.toLowerCase();
      var accentMatches = (!itemAccent) || (itemAccent.toLowerCase() === currentAccent.toLowerCase());
      
      if (mode === 'plain' && bgMatches && accentMatches) {
        badge.style.display = 'flex';
      } else {
        badge.style.display = 'none';
      }
    });
  },

  // Set both background + accent from a swatch preset
  applySwatchPreset: function (bg, accent) {
    localStorage.setItem('tom-labs-accent-color', accent);
    this.setPlainColor(bg);
    this.setAccentColor(accent);
    this.setMode('plain');
  },

  toHex: function (color) {
    if (!color) return "#010d12";
    if (color.indexOf("#") === 0) return color;
    var rgb = color.match(/\d+/g);
    if (!rgb || rgb.length < 3) return "#010d12";
    var r = parseInt(rgb[0]),
      g = parseInt(rgb[1]),
      b = parseInt(rgb[2]);
    return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
  },

  initScenery: function () {
    if (document.querySelector(".scenery-container")) return;
    var container = document.createElement("div");
    container.className = "scenery-container";
    container.innerHTML = `
      <div class="scenery-snow"></div>
      <div class="scenery-mountains"></div>
      <div class="scenery-house"></div>
      <div class="scenery-orb-1"></div>
      <div class="scenery-orb-2"></div>
    `;
    document.body.appendChild(container);
  },

  _syncTimer: null,
  syncToServer: function () {
    var mode = localStorage.getItem("tom-labs-bg-mode");
    var plainColor = localStorage.getItem("tom-labs-plain-color");
    var accentColor = localStorage.getItem("tom-labs-accent-color");
    var customSlots = [];
    var customThemes = [];
    for (var i = 0; i < 10; i++) {
      customSlots.push(localStorage.getItem("tom-labs-custom-color-" + i));
      customThemes.push(localStorage.getItem("tom-labs-custom-theme-" + i));
    }

    if (this._syncTimer) clearTimeout(this._syncTimer);
    this._syncTimer = setTimeout(function () {
      fetch('/api/account/theme_save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          mode: mode,
          plainColor: plainColor,
          accentColor: accentColor,
          customSlots: customSlots,
          customThemes: customThemes
        })
      }).catch(function (err) { console.error("Theme sync failed:", err); });
    }, 500);
  }
};

document.addEventListener("DOMContentLoaded", function () { TomBG.init(); });

/**
 * Theme & Visuals Controller (Migrated from _master.php for Grunt Workflow)
 */

window.updateThemeIcon = function (theme) {
  var iconMap = {
    'light': 'bx-sun',
    'dark': 'bx-moon',
    'auto': 'bx-circle-half'
  };
  var iconElement = document.getElementById('currentThemeIcon');
  if (iconElement) {
    iconElement.classList.remove('bx-sun', 'bx-moon', 'bx-circle-half', 'bx-circle');
    iconElement.classList.add(iconMap[theme] || 'bx-circle');
  }
};

window.changeTheme = function (themeName) {
  var themeToApply = themeName;
  if (themeName === 'auto') {
    themeToApply = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  document.documentElement.setAttribute('data-coreui-theme', themeToApply);
  localStorage.setItem('tom-labs-theme', themeName);
  window.updateThemeIcon(themeName);

  document.querySelectorAll('.mode-item').forEach(function (btn) {
    btn.classList.toggle('active', btn.getAttribute('data-coreui-value') === themeName);
  });

  if (window.TomVisuals) {
    window.TomVisuals.switchBGTheme(themeName);
  }
  window.dispatchEvent(new Event('themeChanged'));
};

window.TomVisuals = {
  toggleBlur: function (enable) {
    if (enable) {
      document.documentElement.classList.add('glass-mode');
      localStorage.setItem('tom-labs-visual-blur', 'true');
    } else {
      document.documentElement.classList.remove('glass-mode');
      localStorage.setItem('tom-labs-visual-blur', 'false');
    }
  },
  switchBGTheme: function (theme) {
    var darkGrid = document.getElementById('bg-grid-dark');
    var lightGrid = document.getElementById('bg-grid-light');
    if (!darkGrid || !lightGrid) return;

    var themeToPreview = theme;
    if (theme === 'auto') {
      themeToPreview = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    if (themeToPreview === 'light') {
      darkGrid.style.display = 'none';
      lightGrid.style.display = 'grid';
      lightGrid.classList.remove('d-none');
    } else {
      lightGrid.style.display = 'none';
      darkGrid.style.display = 'grid';
      darkGrid.classList.remove('d-none');
    }
  },
  syncUI: function () {
    var blurToggle = document.getElementById('visualBlurToggle');
    if (blurToggle) {
      var savedBlur = localStorage.getItem('tom-labs-visual-blur');
      blurToggle.checked = (savedBlur !== 'false');
    }
    var savedTheme = localStorage.getItem('tom-labs-theme') || 'dark';
    this.switchBGTheme(savedTheme);
    window.updateThemeIcon(savedTheme);
  }
};

function openConnectionModal(hash, name, status) {
    // 1. Set static info
    document.getElementById('modalLabName').textContent = name;

    // 2. Reset View State
    const loadingEl = document.getElementById('modalLoading');
    const offlineEl = document.getElementById('modalOffline');
    const contentEl = document.getElementById('modalContent');
    const fieldsEl = document.getElementById('connectionFields');

    loadingEl.style.display = 'block';
    offlineEl.style.display = 'none';
    contentEl.style.display = 'none';
    fieldsEl.innerHTML = '';

    // 3. Show Modal
    const modal = new coreui.Modal(document.getElementById('connectionInfoModal'));
    modal.show();

    // 4. Check Status
    if (status !== 'running') {
        loadingEl.style.display = 'none';
        offlineEl.style.display = 'block';
        return;
    }

    // 5. Fetch Technical Connection Info
    fetch(`/api/labs/connection_info?hash=${hash}`)
        .then(response => response.json())
        .then(data => {
            loadingEl.style.display = 'none';

            if (data.status === 'success') {
                contentEl.style.display = 'block';

                // Set Title
                if (data.data.title) {
                    document.querySelector('#connectionInfoModal .modal-title').textContent = data.data.title;
                }

                // Render Technical Fields
                renderConnectionFields(data.data.fields, fieldsEl);

            } else {
                alert('Failed to load connection details: ' + (data.error || 'Unknown error'));
                modal.hide();
            }
        })
        .catch(err => {
            console.error(err);
            loadingEl.style.display = 'none';
            alert('Network error occurred.');
            modal.hide();
        });
}

function renderConnectionFields(fields, container) {
    let html = '<div class="d-flex flex-column gap-3">';
    fields.forEach(field => {
        const isLink = (field.type === 'link');
        const inputType = (field.type === 'password') ? 'password' : 'text';
        const isMono = field.mono ? 'font-monospace' : '';

        let valueHtml = '';

        if (isLink) {
            valueHtml = `
                <a href="${field.value}" target="_blank" class="text-decoration-none small fw-bold">
                    ${field.value} <i class='bx bx-link-external ms-1'></i>
                </a>`;
        } else {
            const copyBtn = field.copy ? `
                <button class="btn btn-outline-secondary ms-2 rounded-pill px-3" 
                        onclick="navigator.clipboard.writeText('${field.value}')">
                    <i class='bx bx-copy'></i>
                </button>` : '';

            valueHtml = `
                <div class="input-group input-group-sm">
                    <input type="${inputType}" 
                        class="form-control rounded-pill border-secondary bg-body-tertiary text-body px-3 ${isMono}" 
                        value="${field.value}" 
                        readonly style="opacity: 0.85; background: rgba(255,255,255,0.05) !important; color: white !important;">
                    ${copyBtn}
                </div>`;
        }

        html += `
            <div class="row align-items-center">
                <div class="col-4 text-white-50 small fw-bold">
                    ${field.label}
                </div>
                <div class="col-8">
                    ${valueHtml}
                </div>
            </div>`;
    });
    html += '</div>';
    container.innerHTML = html;
}
/**
 * Copies raw text string to clipboard and shows toast
 */
function copyText(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast("Text copied to clipboard!");
    });
}

/**
 * Copies value from an input element by ID and shows toast
 */
function copyValue(elementId) {
    const copyText = document.getElementById(elementId);
    if (copyText) {
        navigator.clipboard.writeText(copyText.value).then(() => {
            showToast("Command copied!");
        });
    }
}

/**
 * CoreUI Toast Trigger Function
 */
function showToast(message) {
    const toastEl = document.getElementById('copyToast');
    const messageEl = document.getElementById('toast-message');
    
    // Set the dynamic message
    messageEl.innerText = message;

    // Use CoreUI's built-in Toast component
    const toast = new coreui.Toast(toastEl, {
        delay: 3000, // Show for 3 seconds
        autohide: true
    });
    
    toast.show();

    // Optional: Visual feedback on the clicked button
    if (event && event.currentTarget) {
        const btn = event.currentTarget;
        const icon = btn.querySelector('i');
        const originalClass = icon.className;
        icon.className = 'bx bx-check text-success';
        setTimeout(() => { icon.className = originalClass; }, 2000);
    }
}
// ========================================================================
// Dashboard — Workspace Polling & Insights Animations
// ========================================================================

(function () {
    let pollingIntervals = {};

    function startMetricsPolling(hash) {
        if (pollingIntervals[hash]) return; // Avoid duplicate intervals

        function fetchMetrics() {
            fetch(`/api/instance/stats?hash=${hash}`)
                .then(res => res.json())
                .then(stats => {
                    const cpuEl = document.getElementById(`cpu-${hash}`);
                    const memEl = document.getElementById(`mem-${hash}`);
                    const loadEl = document.getElementById(`load-${hash}`);

                    if (stats.CPUPerc && cpuEl) cpuEl.textContent = stats.CPUPerc;
                    if (stats.MemUsage && memEl) {
                        const usage = stats.MemUsage.split(' / ')[0];
                        memEl.textContent = usage;
                    }
                    if (stats.Load1 !== undefined && loadEl) {
                        const loadAvg = `${stats.Load1.toFixed(2)}, ${stats.Load5.toFixed(2)}, ${stats.Load15.toFixed(2)}`;
                        loadEl.textContent = loadAvg;
                    }
                })
                .catch(() => { });
        }
        fetchMetrics();
        pollingIntervals[hash] = setInterval(fetchMetrics, 5000);
    }

    // Export initialization function globally
    window.initDashboardPolling = function (hashes) {
        if (Array.isArray(hashes)) {
            hashes.forEach(hash => {
                startMetricsPolling(hash);
            });
        }
    };

    // Initialize Smart Insights activity graph
    window.initDashboardInsights = function () {
        const subtitle = document.getElementById('insights-subtitle');
        const peakLabel = document.getElementById('insights-peak-label');
        const footer = document.getElementById('insights-footer');
        const activeDays = document.getElementById('insights-active-days');
        const lastSeen = document.getElementById('insights-last-seen');

        if (!subtitle || !peakLabel) return;

        fetch('/api/dashboard/insights')
            .then(res => res.json())
            .then(data => {
                if (data.has_data) {
                    subtitle.textContent = "You're most productive between";
                    peakLabel.textContent = data.peak_label;

                    // Animate bars with theme-aware colors
                    const isLight = document.documentElement.getAttribute('data-coreui-theme') === 'light';
                    const bars = document.querySelectorAll('.insights-bar');
                    const barValues = data.bars || [];
                    bars.forEach((bar, i) => {
                        const val = barValues[i] || 0;
                        const minHeight = val > 0 ? Math.max(val, 6) : 3;
                        setTimeout(() => {
                            bar.style.height = minHeight + '%';
                            if (val >= 70) {
                                // Peak hours — orange
                                bar.style.background = '#ffa502';
                                bar.style.boxShadow = isLight ? '0 0 6px rgba(255, 165, 2, 0.45)' : '0 0 8px rgba(255, 165, 2, 0.35)';
                            } else if (val > 0) {
                                // Active hours
                                bar.style.background = isLight ? 'rgba(0, 0, 0, 0.16)' : 'rgba(255, 255, 255, 0.22)';
                                bar.style.boxShadow = 'none';
                            } else {
                                // Inactive — very subtle
                                bar.style.background = isLight ? 'rgba(0, 0, 0, 0.06)' : 'rgba(255, 255, 255, 0.08)';
                                bar.style.boxShadow = 'none';
                            }
                        }, i * 25);
                    });

                    // Show footer stats
                    if (data.active_days > 0 && activeDays && footer) {
                        activeDays.textContent = data.active_days + ' active days';
                        footer.style.cssText = '';
                        footer.classList.add('d-flex');
                    }
                    if (data.last_seen && lastSeen) {
                        lastSeen.textContent = 'Last: ' + data.last_seen;
                    }
                } else {
                    subtitle.textContent = "Start exploring to see your insights";
                    peakLabel.textContent = "No data yet";
                    peakLabel.style.fontSize = '1.2rem';
                    peakLabel.style.opacity = '0.4';
                }
            })
            .catch(() => {
                subtitle.textContent = "Start exploring to see your insights";
                peakLabel.textContent = "No data yet";
            });
    };

    // Tab Switcher and selection persistence
    window.switchContinueTab = function (tabId) {
        // 1. Update buttons by toggling active class cleanly
        const buttons = document.querySelectorAll('.continue-tab-btn');
        buttons.forEach(btn => {
            if (btn.getAttribute('data-tab') === tabId) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        // 2. Toggle panes
        const panes = document.querySelectorAll('.continue-tab-pane');
        panes.forEach(pane => {
            if (pane.id === 'continue-pane-' + tabId) {
                pane.classList.remove('d-none');
            } else {
                pane.classList.add('d-none');
            }
        });

        // 3. Persist the active tab in localStorage
        try {
            localStorage.setItem('active_continue_tab', tabId);
        } catch (e) {
            console.error('Failed to persist active tab:', e);
        }
    };

    // Restore previously active tab on page load
    document.addEventListener('DOMContentLoaded', function () {
        try {
            const savedTab = localStorage.getItem('active_continue_tab');
            if (savedTab) {
                const pane = document.getElementById('continue-pane-' + savedTab);
                if (pane) {
                    window.switchContinueTab(savedTab);
                }
            }
        } catch (e) {
            console.error('Failed to restore active tab:', e);
        }
    });
})();


let activeConfigRaw = "";

function toggleManualKey() {
    document.getElementById('manualKeyArea').style.display = document.getElementById('autoGenCheck').checked ? 'none' :
        'block';
}

function generateWGKeypair() {
    const keyPair = nacl.box.keyPair();
    return {
        private: btoa(String.fromCharCode(...keyPair.secretKey)),
        public: btoa(String.fromCharCode(...keyPair.publicKey))
    };
}

async function openVPNConnectionModal(id, name) {
    // 1. Show Loading State (could use a spinner in modal if needed)
    console.log("Fetching VPN config for:", id);

    try {
        const response = await fetch(`/api/vpn/connection_info?id=${id}`);
        const data = await response.json();

        if (data.status === 'success') {
            showConfig(name, data.data.config_raw);
        } else {
            alert('Failed to load configuration: ' + (data.error || 'Unknown error'));
        }
    } catch (err) {
        console.error(err);
        alert('Network error occurred.');
    }
}

function showConfig(name, configRaw) {
    activeConfigRaw = configRaw;
    const html = activeConfigRaw
        .replace(/\[Interface\]/g, '<span class="config-header">[Interface]</span>')
        .replace(/\[Peer\]/g, '<span class="config-header">[Peer]</span>')
        .replace(/(PrivateKey|Address|DNS|PublicKey|AllowedIPs|Endpoint|PersistentKeepalive)/g,
            '<span class="config-label">$1</span>')
        .replace(/ = (.*)/g, ' = <span class="config-value">$1</span>');

    document.getElementById('configText').innerHTML = html.replace(/\n/g, '<br>');
    document.getElementById('configTitle').innerText = "Wireguard Config: " + name;

    const qrEl = document.getElementById("qrcode");
    qrEl.innerHTML = "";
    const qrcode = new QRCode(qrEl, {
        text: activeConfigRaw,
        width: 200,
        height: 200,
        correctLevel: QRCode.CorrectLevel.H
    });

    setTimeout(() => {
        const canvas = qrEl.querySelector('canvas');
        if (canvas) {
            const ctx = canvas.getContext('2d');
            const center = canvas.width / 2;
            const bSize = canvas.width * 0.28;
            ctx.fillStyle = "#ffffff";
            ctx.fillRect(center - bSize / 2, center - bSize / 2, bSize, bSize);
            ctx.fillStyle = "#0b0e14";
            ctx.font = "bold 16px Arial";
            ctx.textAlign = "center";
            ctx.textBaseline = "middle";
            ctx.fillText("TOM", center, center);
        }
    }, 120);
    new coreui.Modal(document.getElementById('configModal')).show();
}

function copyConfig() {
    navigator.clipboard.writeText(activeConfigRaw);
    alert("Config Copied!");
}

async function downloadTunnel(name, deviceId) {
    try {
        const response = await fetch(`/api/vpn/connection_info?id=${deviceId}`);
        const data = await response.json();

        if (data.status === 'success') {
            const blob = new Blob([data.data.config_raw], {
                type: 'text/plain'
            });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `${name.replace(/\s+/g, '_')}.conf`;
            link.click();
        } else {
            alert("Download failed: " + data.error);
        }
    } catch (err) {
        alert("Network Error during download");
    }
}

const vpnForm = document.getElementById('vpnAddForm');
if (vpnForm) {
    vpnForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        if (document.getElementById('autoGenCheck').checked) {
            const keys = generateWGKeypair();
            document.getElementById('hiddenPrivKey').value = keys.private;
            document.getElementById('hiddenPubKey').value = keys.public;
        } else {
            document.getElementById('hiddenPubKey').value = document.getElementById('inputPubKey').value;
            document.getElementById('hiddenPrivKey').value = "";
        }
        btn.disabled = true;
        btn.innerText = 'Provisioning...';
        try {
            const res = await fetch('/api/vpn/add', {
                method: 'POST',
                body: new FormData(this)
            });
            if ((await res.json()).status === 'success') window.location.reload();
        } catch (err) {
            alert("Network Error");
        } finally {
            btn.disabled = false;
            btn.innerText = 'Verify and Add';
        }
    });
}

async function deleteDevice(dbId, pubKey) {
    if (!confirm("Delete this device?")) return;
    const res = await fetch('/api/vpn/delete', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            id: dbId,
            public_key: pubKey
        })
    });
    if ((await res.json()).status === 'success') window.location.reload();
}

document.addEventListener('DOMContentLoaded', () => {
    let statsInterval = null;

    async function fetchStats() {
        // 1. Page Check: Only run if device rows exist
        if (document.querySelectorAll('.device-row').length === 0) return;

        try {
            // 2. Clean URL
            const response = await fetch('/api/vpn/stats');
            const data = await response.json();

            document.querySelectorAll('.device-row').forEach(card => {
                const live = data.find(d => d.id === card.dataset.pubkey);
                // 3. Auto-detect Offline/Online
                if (live) {
                    const pill = card.querySelector('.status-pill');
                    const icon = live.status === 'Online' ? 'bx-wifi' : (live.status === 'Unreachable' ? 'bx-time-five' : 'bx-wifi-off');

                    pill.innerHTML = `<i class='bx ${icon} me-1'></i> ${live.status}`;
                    pill.className = `badge rounded-pill small status-pill bg-${live.color} px-2 py-1 d-flex align-items-center`;

                    // Update Stats
                    const rxEl = card.querySelector('.rx-val');
                    const txEl = card.querySelector('.tx-val');
                    const originEl = card.querySelector('.origin-val');

                    if (rxEl) rxEl.innerText = live.rx;
                    if (txEl) txEl.innerText = live.tx;
                    if (originEl) originEl.innerText = live.origin;
                }
            });
        } catch (e) {
            console.warn("VPN Stats Error:", e);
        }
    }

    // 4. Smart Polling Controller
    function startPolling() {
        if (statsInterval) clearInterval(statsInterval);
        fetchStats(); // Capture fast (Immediate run)
        statsInterval = setInterval(fetchStats, 3000); // 3s for faster updates
    }

    function stopPolling() {
        if (statsInterval) {
            clearInterval(statsInterval);
            statsInterval = null;
        }
    }

    // 5. Visibility Handler
    document.addEventListener("visibilitychange", () => {
        if (document.hidden) {
            stopPolling();
        } else {
            startPolling(); // Resumes and fetches immediately
        }
    });

    // Start if visible
    if (!document.hidden && document.querySelectorAll('.device-row').length > 0) {
        startPolling();
    }
});

async function addDomain() {
  const provider = document.getElementById("dns_provider").value;
  const prefix = document.getElementById("choose_domain").value.trim();
  const btn = document.getElementById("btn_verify_add");

  if (!prefix) return alert("Domain prefix is required.");

  let finalDomain =
    provider === "custom" ? prefix : prefix + provider.replace("*", "");
  let type = provider === "custom" ? "custom" : "tom";

  btn.disabled = true;
  btn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-2"></span> Verifying...';

  try {
    const response = await fetch("/src/api/domain/add_domain.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ domain: finalDomain, type: type }),
    });

    const result = await response.json();

    if (result.success) {
      if (result.verified) {
        alert(`✅ Success! ${finalDomain} has been verified and added.`);
      } else {
        alert(
          `⚠️ Domain added but NOT verified yet.\n\nPlease point the A record to: 106.51.76.75\n\nThen click "Verify DNS" to check again.`,
        );
      }
      location.reload();
    } else {
      alert("❌ Error: " + result.error);
    }
  } catch (e) {
    console.error("Error details:", e);
    alert("Connection error occurred. Check console for details.");
  } finally {
    btn.disabled = false;
    btn.innerHTML = "Verify and Add";
  }
}

async function removeDomain(domainId) {
  if (
    !confirm(
      "Are you sure? This will break any active web links for this domain.",
    )
  )
    return;

  try {
    const response = await fetch("/src/api/domain/remove_domain.php", {
      method: "POST",
      body: JSON.stringify({ domain_id: domainId }),
    });

    const result = await response.json();
    if (result.success) {
      location.reload(); // Refresh the grid
    } else {
      alert("❌ " + result.error);
    }
  } catch (e) {
    alert("Connection error.");
  }
}

// Add this function for manual re-verification
async function verifyDomain(domainId) {
  try {
    const response = await fetch("/src/api/domain/verify_domain.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ domain_id: domainId }),
    });

    const result = await response.json();

    if (result.success) {
      if (result.verified) {
        alert("✅ Domain verified successfully!");
      } else {
        alert(
          "❌ Domain NOT verified. Please ensure A record points to: 106.51.76.75",
        );
      }
      location.reload();
    } else {
      alert("Error: " + result.error);
    }
  } catch (e) {
    alert("Connection error: " + e.message);
  }
}

function openCodeModal(hash, name, status) {
    // 1. Set static info
    const modalEl = document.getElementById('codeInfoModal');
    if (!modalEl) return;

    document.getElementById('codeModalLabName').textContent = name;

    // 2. Reset View State
    const loadingEl = document.getElementById('codeModalLoading');
    const offlineEl = document.getElementById('codeModalOffline');
    const contentEl = document.getElementById('codeModalContent');
    const fieldsEl = document.getElementById('codeFields');
    const actionBtn = document.getElementById('codeModalActionBtn');

    loadingEl.style.display = 'block';
    offlineEl.style.display = 'none';
    contentEl.style.display = 'none';
    fieldsEl.innerHTML = '';
    actionBtn.innerHTML = '';

    // 3. Show Modal
    const modal = new coreui.Modal(modalEl);
    modal.show();

    // 4. Check Status
    if (status !== 'running') {
        loadingEl.style.display = 'none';
        offlineEl.style.display = 'block';
        return;
    }

    // 5. Fetch Code Info
    fetch(`/api/labs/code_info?hash=${hash}`)
        .then(res => res.json())
        .then(data => {
            loadingEl.style.display = 'none';
            if (data.status === 'success') {
                contentEl.style.display = 'block';

                // Title
                if (data.data.title) {
                    modalEl.querySelector('.modal-title').textContent = data.data.title;
                }

                let html = '';

                // Description (Bullets)
                if (data.data.description) {
                    html += `<div class="mb-4">
                        <h6 class="fw-bold text-white small mb-2">What can you do here?</h6>
                        <ul class="text-white-50 small ps-3 mb-0">
                            ${data.data.description.map(d => `<li>${d}</li>`).join('')}
                        </ul>
                    </div>`;
                }

                // Instruction
                html += `<div class="mb-4 text-white small fw-medium">${data.data.instruction}</div>`;

                // Primary Credential
                if (data.data.primary) {
                    html += `
                        <div class="row align-items-center mb-4">
                            <div class="col-5 text-white small fw-bold">${data.data.primary.label}</div>
                            <div class="col-7">
                                <div class="input-group input-group-sm">
                                    <input type="password" class="form-control rounded-pill-start border-secondary bg-dark text-white px-3 font-monospace" value="${data.data.primary.value}" readonly>
                                    <button class="btn btn-outline-secondary rounded-pill-end px-3 border-start-0" onclick="navigator.clipboard.writeText('${data.data.primary.value}')">
                                        <i class='bx bx-copy'></i>
                                    </button>
                                </div>
                            </div>
                        </div>`;
                }

                // Tip
                html += `<div class="mt-4"><p class="text-white-50 small"><span class="fw-bold text-white">Tip:</span> If there is an error while logging in, redeploy and try again.</p></div>`;

                fieldsEl.innerHTML = html;

                // Action Button
                if (data.data.action) {
                    actionBtn.innerHTML = `<a href="${data.data.action.link}" target="_blank" class="btn btn-primary fw-bold px-4 rounded-pill">${data.data.action.label}</a>`;
                }

            } else {
                alert('Error: ' + data.error);
                modal.hide();
            }
        })
        .catch(() => {
            loadingEl.style.display = 'none';
            alert('Network error');
            modal.hide();
        });
}

/**
 * ============================================================================
 * TOM LABS - LAB DASHBOARD CONTROLLER
 * ============================================================================
 * Handles all lab-specific operations: deployment, monitoring, UI updates
 *
 * Dependencies:
 * - mq-client.js (OverviewSocket, LogSocket, ActivityTracker)
 * - chart.js
 * - coreui.js
 *
 * ============================================================================
 */

const Dashboard = {
  /* ========================================================================
   * STATE MANAGEMENT
   * ====================================================================== */
  isProcessing: false,
  statsInterval: null,
  charts: {},
  historyLimit: 20,
  isFirstLoad: true,

  /* ========================================================================
   * INITIALIZATION
   * ====================================================================== */

  /**
   * Main initialization - called on page load
   */
  init: function () {
    console.log("[Dashboard] Initializing...");

    // 1. Initialize Charts
    this.initCharts();

    // 2. Initial Terminal Setup
    this.resetTerminal();
    this.appendCommand("");

    // 3. Start Services
    this.initSockets();
    this.startStatsPolling();

    // 4. Initialize Optimization Tracker
    if (typeof ActivityTracker !== "undefined") {
      ActivityTracker.init();
    }

    console.log("[Dashboard] Initialization complete.");
  },

  /* ========================================================================
   * SOCKET MANAGEMENT
   * ====================================================================== */

  /**
   * Toggle button loading state
   * @param {HTMLElement} btn 
   * @param {boolean} show 
   */
  toggleLoading: function (btn, show) {
    if (!btn) return;

    if (show) {
      const type = btn.getAttribute('data-coreui-spinner-type') || 'border';
      const spinner = `<span class="spinner-${type} spinner-${type}-sm me-2" role="status" aria-hidden="true"></span>`;

      if (!btn.dataset.originalContent) {
        btn.dataset.originalContent = btn.innerHTML;
      }

      btn.classList.add('disabled');
      // Fix: Only show Spinner + Text (Hide original Icon)
      btn.innerHTML = spinner + btn.textContent.trim();
    } else {
      if (btn.dataset.originalContent) {
        btn.innerHTML = btn.dataset.originalContent;
      }
      btn.classList.remove('disabled');
    }
  },

  /**
   * Initialize Socket Connections (Overview and Instance Logs)
   */
  initSockets: function () {
    // console.log("[Dashboard] Initializing sockets...");

    // 1. Connect to Global Sidebar Stats (Overview)
    try {
      if (!OverviewSocket.isConnected) {
        OverviewSocket.connect("labs_overview", (data) =>
          this.updateSidebar(data),
        );
      }
    } catch (e) {
      console.error("[Dashboard] OverviewSocket connection failed:", e);
    }

    // 2. Connect to Instance Logs (Only if session exists)
    try {
      if (window.SESSION_HASH && !LogSocket.isConnected) {
        const dot = document.getElementById("mq-status-dot");
        // Only connect if the UI element exists or we handle the null UI gracefully
        const ui = dot ? { dot: dot } : null;

        LogSocket.connect(
          `logs.${window.SESSION_HASH}`,
          (data) => this.appendLog(data),
          ui,
        );
      }
    } catch (e) {
      console.error("[Dashboard] LogSocket connection failed:", e);
    }
  },

  /* ========================================================================
   * CHART MANAGEMENT
   * ====================================================================== */

  /**
   * Initialize all monitoring charts
   */
  initCharts: function () {
    const create = (id, color, type = "line", extraOptions = {}) => {
      const ctx = document.getElementById(id);
      if (!ctx) return;

      const isBar = type === "bar";
      const limit = extraOptions.limit || this.historyLimit;

      const datasets = extraOptions.datasets || [
        {
          data: new Array(limit).fill(0),
          borderColor: color,
          backgroundColor: isBar ? color : "transparent",
          borderWidth: isBar ? 0 : 2,
          pointRadius: 0,
          tension: extraOptions.tension !== undefined ? extraOptions.tension : 0.4,
          fill: extraOptions.fill || false,
          barThickness: extraOptions.barThickness || 'flex',
          borderRadius: 2
        },
      ];

      this.charts[id] = new Chart(ctx, {
        type: type,
        data: {
          labels: new Array(limit).fill(""),
          datasets: datasets,
        },
        options: {
          maintainAspectRatio: false,
          animation: {
            duration: 300,
            easing: 'linear'
          },
          plugins: {
            legend: { display: false },
            tooltip: { enabled: false }
          },
          scales: {
            x: { display: false },
            y: {
              display: false,
              suggestedMin: 0
            }
          },
          ...extraOptions.options
        },
      });
      this.charts[id].limit = limit;
    };

    // SNA Colors
    const colors = {
      cyan: "#00e5ff",
      green: "#00ff88",
      yellow: "#ffcc00",
      red: "#ff4444",
      blue: "#3d5afe"
    };

    // Initialize all charts with SNA-style configurations
    // Net IO: Download (Purple) & Upload (Cyan)
    create("chart-net-io", "#8b91f9", "line", {
      tension: 0.4,
      limit: 30,
      datasets: [
        { label: 'Download', borderColor: "#8b91f9", data: new Array(30).fill(0), borderWidth: 2, pointRadius: 0, tension: 0.4 },
        { label: 'Upload', borderColor: "#50c7f6", data: new Array(30).fill(0), borderWidth: 2, pointRadius: 0, tension: 0.4 }
      ]
    });

    // Block IO: Read (Yellow) & Write (Green)
    create("chart-block-io", "#f2b90d", "line", {
      tension: 0.4,
      limit: 30,
      datasets: [
        { label: 'Read', borderColor: "#f2b90d", data: new Array(30).fill(0), borderWidth: 2, pointRadius: 0, tension: 0.4 },
        { label: 'Write', borderColor: "#55b16e", data: new Array(30).fill(0), borderWidth: 2, pointRadius: 0, tension: 0.4 }
      ]
    });

    // Averages use varying densities and wide bars
    create("chart-avg-1", colors.blue, "bar", { barThickness: 15, limit: 6 });
    create("chart-avg-5", colors.yellow, "bar", { barThickness: 4, limit: 15 });
    create("chart-avg-15", colors.green, "bar", { barThickness: 4, limit: 15 });

    // History uses jagged line charts
    create("chart-peak-cpu", colors.cyan, "line", { tension: 0.4, limit: 30 });
    create("chart-max-pid", colors.red, "line", {
      tension: 0.4,
      limit: 30,
      options: {
        scales: {
          y: {
            display: false,
            suggestedMin: 0,
            suggestedMax: 150 // Better context for standard PID counts (~80-100)
          }
        }
      }
    });
    create("chart-high-mem", colors.yellow, "line", { tension: 0.4, limit: 30 });
  },

  /**
   * Push new data point to a chart
   * @param {string} id - Chart ID
   * @param {number} value - Data value
   */
  pushChartData: function (id, value) {
    const chart = this.charts[id];
    if (!chart) return;

    const limit = chart.limit || this.historyLimit;

    // Handle multi-dataset vs single dataset
    if (Array.isArray(value)) {
      value.forEach((v, i) => {
        if (chart.data.datasets[i]) {
          const d = chart.data.datasets[i].data;
          d.push(v);
          if (d.length > limit) d.shift();
        }
      });
    } else {
      const d = chart.data.datasets[0].data;
      d.push(value);
      if (d.length > limit) d.shift();
    }

    chart.update("none");
  },

  /* ========================================================================
   * UI UPDATES - SIDEBAR
   * ====================================================================== */

  /**
   * Update sidebar stats from MQ data
   * @param {object} data - System stats from MQ
   */
  updateSidebar: function (data) {
    // Remove loading state on first update
    const container = document.getElementById("sidebar-stats-container");
    if (container && container.classList.contains("loading-state")) {
      container.classList.remove("loading-state");
      container.querySelectorAll(".progress-bar").forEach((el) => {
        el.classList.remove("placeholder-shimmer");
        el.style.width = "0%";
      });
    }

    // Update CPU bars
    if (data.cpu) {
      data.cpu.forEach((val, i) => {
        const bar = document.querySelector(`.cpu-${i}`);
        if (bar) bar.style.width = val + "%";
      });

      const avg = (
        data.cpu.reduce((a, b) => a + b, 0) / data.cpu.length
      ).toFixed(2);
      document.getElementById("sidebar-cpu-val").innerText = avg + "%";
    }

    // Update Load Average
    if (data.loadavg) {
      document.getElementById("sidebar-load-val").innerText =
        `Load Avg: ${data.loadavg[0].toFixed(2)}, ${data.loadavg[1].toFixed(2)}, ${data.loadavg[2].toFixed(2)}`;
    }

    // Update Memory
    if (data.mem) {
      const used = (data.mem.used / 1073741824).toFixed(2);
      const total = (data.mem.total / 1073741824).toFixed(2);
      const avail = (data.mem.available / 1073741824).toFixed(2);
      const perc = (data.mem.used / data.mem.total) * 100;

      document.getElementById("sidebar-mem-bar").style.width = perc + "%";
      document.getElementById("sidebar-mem-details").innerText =
        `${used} GiB / ${total} GiB Avail: ${avail} GiB`;
    }

    // Update Swap
    if (data.swap) {
      const sUsed = (data.swap.used / 1048576).toFixed(2);
      const sTotal = (data.swap.total / 1073741824).toFixed(2);
      const sFree = (data.swap.free / 1073741824).toFixed(2);

      document.getElementById("sidebar-swap-bar").style.width =
        data.swap.percent + "%";
      document.getElementById("sidebar-swap-details").innerText =
        `${sUsed} MiB / ${sTotal} GiB Free: ${sFree} GiB`;
    }
  },

  /* ========================================================================
   * UI UPDATES - INSTANCE STATS
   * ====================================================================== */

  /**
   * Start polling instance stats from API
   */
  startStatsPolling: function () {
    // 1. Only poll if we have a valid session hash (Dashboard Page)
    if (!window.SESSION_HASH) {
      // console.log("[Dashboard] No active session hash found. Stats polling disabled.");
      return;
    }

    const start = () => {
      if (this.statsInterval) clearInterval(this.statsInterval);

      const poll = () => {
        if (document.hidden) return; // double check

        fetch(`/api/instance/stats?hash=${window.SESSION_HASH}`)
          .then((res) => res.json())
          .then((data) => {
            if (data.status === "offline" || data.status === "initializing") {
              this.updateUIIdle();
            } else {
              this.updateUI(data);
            }
          })
          .catch((err) => {
            console.warn("Stats Fetch Error:", err.message);
            this.updateUIIdle();
          });
      };

      poll(); // Initial call
      this.statsInterval = setInterval(poll, 5000); // Standard 5s polling to save resources
    };

    const stop = () => {
      if (this.statsInterval) {
        clearInterval(this.statsInterval);
        this.statsInterval = null;
      }
    };

    // 2. Handle Visibility Changes to save resources
    document.addEventListener("visibilitychange", () => {
      if (document.hidden) {
        stop();
      } else {
        start();
      }
    });

    // Start initially if visible
    if (!document.hidden) {
      start();
    }
  },

  /**
   * Update UI with live stats
   * @param {object} data - Stats from API
   */
  updateUI: function (data) {
    // Update text stats
    const pidContainer = document.getElementById("stat-pid-container");
    if (pidContainer) pidContainer.style.display = "block";
    document.getElementById("stat-cpu-usage").innerText = data.CPUPerc;
    document.getElementById("stat-cpu-bar").style.width = data.CPUPerc;
    document.getElementById("stat-pid-count").innerText = data.PIDs;
    document.getElementById("stat-mem-perc").innerText = data.MemPerc;
    document.getElementById("stat-mem-bar").style.width = data.MemPerc;
    document.getElementById("stat-mem-info").innerText = data.MemUsage;

    document.getElementById("stat-load-1").innerText = parseFloat(
      data.Load1,
    ).toFixed(4);
    document.getElementById("stat-load-5").innerText = parseFloat(
      data.Load5,
    ).toFixed(4);
    document.getElementById("stat-load-15").innerText = parseFloat(
      data.Load15,
    ).toFixed(4);

    document.getElementById("stat-peak-cpu").innerText = data.PeakCPU;
    document.getElementById("stat-max-pid").innerText = data.MaxPID;
    document.getElementById("stat-high-mem").innerText = data.HighMem;
    document.getElementById("stat-net-io").innerText = data.NetIO;
    document.getElementById("stat-block-io").innerText = data.BlockIO;

    // Update charts (first load vs. incremental)
    if (this.isFirstLoad && data.cpu_h) {
      const mapping = {
        "chart-peak-cpu": data.cpu_h,
        "chart-high-mem": data.mem_h,
        "chart-net-io": data.net_h,
        "chart-block-io": data.block_h,
        "chart-max-pid": data.pids_h,
        "chart-avg-1": data.l1_h,
        "chart-avg-5": data.l5_h,
        "chart-avg-15": data.l15_h,
      };

      for (let id in mapping) {
        if (this.charts[id])
          this.charts[id].data.datasets[0].data = mapping[id];
      }

      Object.values(this.charts).forEach((c) => c.update());
      this.isFirstLoad = false;
    } else {
      // Incremental updates
      // Parse composite strings for IO charts (e.g., "1.46kB / 358B")
      const parseIO = (str) => {
        if (!str) return [0, 0];
        const parts = str.split(' / ').map(p => parseFloat(p) || 0);
        return parts.length === 2 ? parts : [parts[0] || 0, 0];
      };

      this.pushChartData("chart-net-io", parseIO(data.NetIO));
      this.pushChartData("chart-block-io", parseIO(data.BlockIO));

      this.pushChartData("chart-peak-cpu", parseFloat(data.CPUPerc));
      this.pushChartData("chart-high-mem", parseFloat(data.MemPerc));
      this.pushChartData("chart-max-pid", parseInt(data.PIDs));

      this.pushChartData("chart-avg-1", data.Load1);
      this.pushChartData("chart-avg-5", data.Load5);
      this.pushChartData("chart-avg-15", data.Load15);
    }

    // Update status badge
    const badgeArea = document.getElementById("badge-area");
    if (badgeArea && !badgeArea.innerHTML.includes("Running")) {
      badgeArea.innerHTML = `<span class="badge text-bg-success border-0 px-2 py-1 small pulse">Running</span>`;
    }
  },

  /**
   * Reset UI to idle/offline state
   */
  updateUIIdle: function () {
    const resets = {
      "stat-cpu-usage": "0.00%",
      "stat-mem-perc": "0.00%",
      "stat-load-1": "0.0000",
      "stat-load-5": "0.0000",
      "stat-load-15": "0.0000",
      "stat-peak-cpu": "0.00%",
      "stat-net-io": "0B / 0B",
      "stat-block-io": "0B / 0B",
      "stat-max-pid": "0",
      "stat-high-mem": "0.00 MB",
    };

    for (let id in resets) {
      const el = document.getElementById(id);
      if (el) el.innerText = resets[id];
    }

    ["stat-cpu-bar", "stat-mem-bar"].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.style.width = "0%";
    });

    const memInfo = document.getElementById("stat-mem-info");
    if (memInfo) memInfo.innerText = "Lab Offline";

    const badgeArea = document.getElementById("badge-area");
    if (badgeArea) {
      badgeArea.innerHTML = `<span class="badge text-bg-danger border-0 px-2 py-1 small">Offline</span>`;
    }

    const pidContainer = document.getElementById("stat-pid-container");
    if (pidContainer) pidContainer.style.display = "none";

    // Reset charts
    Object.values(this.charts).forEach((chart) => {
      chart.data.datasets[0].data = new Array(this.historyLimit).fill(null);
      chart.update();
    });
  },

  /* ========================================================================
   * TERMINAL / LOG MANAGEMENT
   * ====================================================================== */

  /**
   * Clear terminal display
   */
  resetTerminal: function () {
    const container = document.getElementById("live-logs-container");
    if (container) container.innerHTML = "";
  },

  /**
   * Append a command prompt line to terminal
   * @param {string} cmd - Command text
   */
  appendCommand: function (cmd) {
    const container = document.getElementById("live-logs-container");
    if (!container) return;

    const user = window.LAB_USER || "tom";
    const host = "Tomlabs";
    const div = document.createElement("div");
    div.className = "log-entry py-1";
    div.innerHTML = `<span class="term-user">${user}</span>@<span class="term-host" style="color:#FFA500;">${host}</span> <span class="term-symbol">$</span> <span class="text-white">${cmd}</span>`;
    container.appendChild(div);
  },

  /**
   * Append a log message to terminal (called from MQ)
   * @param {object|string} data - Log data from MQ
   */
  appendLog: function (data) {
    const container = document.getElementById("live-logs-container");
    if (!container) return;

    // Extract message
    let msg = data.log || data.message || data;

    // Clean up message formatting
    msg = msg.replace(/^\[\d{2}:\d{2}:\d{2}\]\s*/, "");
    msg = msg.replace(/^(\[\*\]\s*)+/, "[*] ");
    msg = msg.replace(/^(\[!\]\s*)+/, "[!] ");

    // Create log entry
    const div = document.createElement("div");
    div.className = "log-entry py-1 animate__animated animate__fadeIn";

    // Color coding
    if (msg.startsWith("[✓]")) div.style.color = "#a6e3a1";
    if (msg.startsWith("[!]")) div.style.color = "#f38ba8";

    div.innerText = msg;
    container.appendChild(div);

    // Auto-scroll to bottom
    const viewport = document.getElementById("terminal-viewport");
    if (viewport) viewport.scrollTop = viewport.scrollHeight;

    // Check for completion messages
    const lower = msg.toLowerCase();
    if (
      msg.includes("[✓]") &&
      (lower.includes("deployment complete") ||
        lower.includes("successfully") ||
        lower.includes("graceful shutdown") ||
        lower.includes("now offline") ||
        lower.includes("complete"))
    ) {
      // Sequence finished
      this.isProcessing = false;

      // Update ActivityTracker
      if (typeof ActivityTracker !== "undefined") {
        ActivityTracker.setProcessing(false);
      }

      // Increased delay to 4s to ensure DB writes are fully committed/visible to PHP
      setTimeout(() => location.reload(), 4000);
    }
  },
};

/* ============================================================================
 * LAB ACTION HANDLERS
 * ========================================================================== */
/**
 * handleDeploy now refreshes the domain chips automatically
 */
async function handleDeploy(btn, labType) {
  if (Dashboard.isProcessing) return;

  // Start loading animation
  Dashboard.toggleLoading(btn, true);

  try {
    const type = labType || window.LAB_TYPE || "essentials";

    // 1. Reset Modal State
    document.getElementById("domain_dropdown").style.display = "none";
    document.getElementById("dropdown_arrow").classList.remove("bx-chevron-up");
    document.getElementById("dropdown_arrow").classList.add("bx-chevron-down");

    // Custom Domain Visibility for MinIO & n8n
    const minioWrapper = document.getElementById("minio_domain_wrapper");
    const n8nWrapper = document.getElementById("n8n_domain_wrapper");
    const vscWrapper = document.getElementById("vsc_domain_wrapper");
    const exposeWrapper = document.getElementById("expose_web_wrapper");
    const domainSelectionWrapper = document.getElementById("domain_selection_wrapper");

    if (type === 'minio') {
      if (minioWrapper) minioWrapper.style.display = 'block';
      if (n8nWrapper) n8nWrapper.style.display = 'none';
      if (vscWrapper) vscWrapper.style.display = 'none';
      if (exposeWrapper) exposeWrapper.style.display = 'none';
      if (domainSelectionWrapper) domainSelectionWrapper.style.display = 'none';
    } else if (type === 'n8n') {
      if (minioWrapper) minioWrapper.style.display = 'none';
      if (n8nWrapper) n8nWrapper.style.display = 'block';
      if (vscWrapper) vscWrapper.style.display = 'none';
      if (exposeWrapper) exposeWrapper.style.display = 'none';
      if (domainSelectionWrapper) domainSelectionWrapper.style.display = 'none';
    } else {
      if (minioWrapper) minioWrapper.style.display = 'none';
      if (n8nWrapper) n8nWrapper.style.display = 'none';
      if (vscWrapper) vscWrapper.style.display = 'flex';
      if (exposeWrapper) exposeWrapper.style.display = 'flex';

      const isExposed = document.getElementById("expose_web_toggle").value === 'true';
      if (domainSelectionWrapper) domainSelectionWrapper.style.display = isExposed ? 'flex' : 'none';
    }

    // 2. Bind the Button
    document.getElementById("redeploy-confirm-btn").onclick = () => executeRedeploy(type);

    // 3. Show Modal
    new coreui.Modal(document.getElementById("redeployModal")).show();

    // 4. PERSISTENCE FIX: Draw chips from existing PHP-checked boxes
    setTimeout(() => {
      updateSelectedDomains();
      updateDomainAvailability();
    }, 200);
  } catch (e) {
    console.error("Deploy Error:", e);
  } finally {
    // Stop loading animation immediately after logic runs (modal opens)
    Dashboard.toggleLoading(btn, false);
  }
}

/**
 * Executes the actual POST request
 */
async function executeRedeploy(labType) {
  const modalEl = document.getElementById("redeployModal");
  const type = labType || window.LAB_TYPE || "essentials";
  const modal = coreui.Modal.getInstance(
    document.getElementById("redeployModal"),
  );

  // 1. Collect form data
  const vscDomain = document.getElementById("vsc_domain_selector").value;
  const exposeWeb = document.getElementById("expose_web_toggle").value;
  const checkedDomains = modalEl.querySelectorAll(".domain-selector:checked");
  const domains = Array.from(checkedDomains).map((cb) => cb.value);

  // Collect MinIO specific domains
  const minioConsole = document.getElementById("minio_console_domain").value;
  const minioApi = document.getElementById("minio_api_domain").value;
  // Collect n8n specific domains
  const n8nDomain = document.getElementById("n8n_domain_selector") ? document.getElementById("n8n_domain_selector").value : '';

  modal.hide();
  Dashboard.isProcessing = true;
  if (typeof ActivityTracker !== "undefined")
    ActivityTracker.setProcessing(true);

  // Add Grow Animation to Deployment Button (matches Stop/Launch behavior)
  const deployBtn = document.getElementById("btn-deploy-action"); // Modal button
  if (deployBtn) {
    deployBtn.classList.add("disabled");
    deployBtn.innerHTML = '<span class="spinner-grow spinner-grow-sm me-2" role="status" aria-hidden="true"></span> Processing';
  }

  // ALSO trigger the main header redeploy button animation
  const headerRedeployBtn = document.querySelector('.btn-redeploy-lab');
  if (headerRedeployBtn) {
    Dashboard.toggleLoading(headerRedeployBtn, true);
  }

  // 2. Log to Terminal (Now shows 'minio' correctly)
  Dashboard.resetTerminal();
  Dashboard.appendCommand(
    `labsctl redeploy ${type} --hash=${window.SESSION_HASH}`,
  );

  // 3. Handshake with PHP API
  const formData = new URLSearchParams();
  formData.append("lab", type);
  formData.append("hash", window.SESSION_HASH);
  formData.append("expose_web", exposeWeb);
  formData.append("code_domain", vscDomain);

  if (type === 'minio') {
    formData.append("minio_console_domain", minioConsole);
    formData.append("minio_api_domain", minioApi);
  } else if (type === 'n8n') {
    formData.append("n8n_domain", n8nDomain);
  }

  domains.forEach((d) => formData.append("domains[]", d));

  const response = await fetch("/api/instance/deploy", {
    method: "POST",
    body: formData,
  });

  const data = await response.json();

  if (data.status === 'success' && data.hash) {
    // UPDATE GLOBAL HASH
    window.SESSION_HASH = data.hash;

    // RECONNECT SOCKET to ensure we are listening to the active channel
    console.log("[Dashboard] specific socket reconnecting to: " + data.hash);
    LogSocket.disconnect();
    setTimeout(() => {
      LogSocket.connect("logs." + data.hash, (d) => Dashboard.appendLog(d));
    }, 100);
  }

  Dashboard.appendLog("[*] Handshake accepted. Starting stream...");
}

/**
 * Handle Stop button click - Show Modal
 */
function handleStop() {
  if (Dashboard.isProcessing) return;
  new coreui.Modal(document.getElementById("stopModal")).show();
}

/**
 * Execute the actual stop request
 */
async function executeStop() {
  const modalEl = document.getElementById("stopModal");
  const modal = coreui.Modal.getInstance(modalEl);
  const btn = document.getElementById("stop-confirm-btn");
  const headerBtn = document.getElementById("btn-stop-action");
  const type = window.LAB_TYPE || "essentials";

  modal.hide();
  Dashboard.isProcessing = true;
  if (typeof ActivityTracker !== "undefined") {
    ActivityTracker.setProcessing(true);
  }

  // Trigger header button animation too
  if (headerBtn) Dashboard.toggleLoading(headerBtn, true);

  // Update modal button state (if visible)
  if (btn) {
    btn.classList.add("disabled");
    btn.innerHTML = '<span class="spinner-grow spinner-grow-sm me-2" role="status" aria-hidden="true"></span> Stopping...';
  }

  Dashboard.resetTerminal();
  Dashboard.appendCommand(`labsctl stop ${type} --hash=${window.SESSION_HASH}`);

  // Wait a bit then stop
  await new Promise((r) => setTimeout(r, 300));
  Dashboard.appendLog("[*] Analyzing active session hooks...");

  try {
    const response = await fetch("/api/instance/stop", {
      method: "POST",
      body: new URLSearchParams({
        lab: type,
        hash: window.SESSION_HASH
      }),
    });

    const data = await response.json();
    if (data.status === 'success') {
      Dashboard.appendLog("[*] Shutdown signal acknowledged. Streaming logs...");
    } else {
      Dashboard.appendLog(`[!] Error: ${data.error || 'Shutdown request failed'}`);
      Dashboard.isProcessing = false;
      if (headerBtn) Dashboard.toggleLoading(headerBtn, false);
    }
  } catch (e) {
    console.error("Stop Error:", e);
    if (headerBtn) Dashboard.toggleLoading(headerBtn, false);
    Dashboard.isProcessing = false;
  }
}

/**
 * Launch Code-Server IDE
 * @param {Event} event
 */
async function launchCodeIDE(event) {
  // 1. Determine Target URL & Mode
  // If we are in MinIO mode, the button might be different?
  // Actually MinIO launch is separate in the modal. 
  // This function is for the main "Code" or "Launch" button on dashboard.

  const type = window.LAB_TYPE || 'essentials';
  let url = window.CODE_SERVER_URL;
  let actionName = "Code-Server";
  let ensureAction = "ensure-codeserver";

  // MinIO Handling
  if (type === 'minio') {
    // For MinIO, we probably just want to open the console
    // But we can still "ensure" the container is running if we want.
    // However currently MinIO doesn't have an "idle timeout" feature planned yet.
    // So we just open the URL.
    if (window.LAB_CONFIG && window.LAB_CONFIG.fields) {
      const consoleField = window.LAB_CONFIG.fields.find(f => f.label === 'MinIO Console Endpoint');
      if (consoleField) url = consoleField.value;
    }
    actionName = "MinIO Console";
    ensureAction = null; // No auto-start logic for MinIO yet
  }

  const btn = event.target.closest("button");
  const originalText = btn.innerHTML;

  // 3. Auto-Start Logic (Only for Code-Server currently)
  // We move this BEFORE the URL check because we might get the URL from the response
  if (ensureAction) {
    // Add spinner to button
    btn.classList.add("disabled");
    btn.innerHTML = '<span class="spinner-grow spinner-grow-sm me-2" role="status" aria-hidden="true"></span> Waking up...';
    Dashboard.resetTerminal();
    Dashboard.appendLog(`[*] Ensuring ${actionName} is running...`);

    try {
      const formData = new URLSearchParams();
      formData.append("lab", type);
      formData.append("hash", window.SESSION_HASH);

      let response = await fetch("/api/instance/ensure_codeserver", {
        method: "POST",
        body: formData
      });

      let data = await response.json();
      if (data.url) {
        url = data.url; // Use fresh URL from backend if provided
        window.CODE_SERVER_URL = url;
        Dashboard.appendLog(`[*] Backend returned URL: ${url}`);
      }

      // Wait for worker logs to show success
      await new Promise((r) => setTimeout(r, 2000));

    } catch (e) {
      console.error("Auto-start failed", e);
      Dashboard.appendLog(`[!] Warning: Auto-start trigger failed. Trying saved URL...`);
    }
  }

  if (!url || url === "") {
    Dashboard.appendLog(`[!] Critical: No URL found for ${actionName}.`);
    alert(`${actionName} URL not found. Please Redeploy your lab.`);
    btn.classList.remove("disabled");
    btn.innerHTML = originalText;
    return;
  }

  // 2. Add spinner to button
  btn.classList.add("disabled");
  btn.innerHTML = '<span class="spinner-grow spinner-grow-sm me-2" role="status" aria-hidden="true"></span> Launching...';

  Dashboard.resetTerminal();

  // 3. Auto-Start Logic (Only for Code-Server currently)
  if (ensureAction) {
    Dashboard.appendLog(`[*] Ensuring ${actionName} is running...`);

    try {
      // Handshake with API to trigger worker
      const formData = new URLSearchParams();
      formData.append("lab", type);
      formData.append("hash", window.SESSION_HASH);

      await fetch("/api/instance/ensure_codeserver", {
        method: "POST",
        body: formData
      });

      // Wait for worker logs to show success
      // We'll give it a few seconds buffer
      await new Promise((r) => setTimeout(r, 2000));

    } catch (e) {
      console.error("Auto-start failed", e);
      Dashboard.appendLog(`[!] Warning: Auto-start trigger failed. Trying direct connection...`);
    }
  }

  // 4. Show validation logs (Visual feedback)
  Dashboard.appendLog(`[*] Connecting to ${url}...`);
  await new Promise((r) => setTimeout(r, 800));

  // 5. Open window
  const newWin = window.open(url, "_blank");

  if (!newWin || newWin.closed || typeof newWin.closed === "undefined") {
    Dashboard.appendLog("[!] Popup Blocked! User intervention required.");
    alert("Your browser blocked the popup. Please allow popups for this site.");
  } else {
    Dashboard.appendLog(`[✓] ${actionName} Launched.`);
  }

  // Cleanup
  await new Promise((r) => setTimeout(r, 500));
  btn.classList.remove("disabled");
  btn.innerHTML = originalText;

  // Close modal if open
  const modalEl = document.getElementById("vscModal");
  if (modalEl) {
    const modal = coreui.Modal.getInstance(modalEl);
    if (modal) modal.hide();
  }
}

/* ============================================================================
 * DOMAIN SELECTION HELPERS (for Redeploy Modal)
 * ========================================================================== */

// Track dropdown state
let domainDropdownOpen = false;

/**
 * Toggle domain dropdown visibility
 *
 * Focuses the hidden input field when the container is clicked
 */
function focusSearch() {
  document.getElementById("domain_search").focus();
}

/**
 * Shows the dropdown when the input is focused or user starts typing
 */
function showDropdown() {
  const dropdown = document.getElementById("domain_dropdown");
  dropdown.style.display = "block";
  document
    .getElementById("dropdown_arrow")
    .classList.replace("bx-chevron-down", "bx-chevron-up");
}

/**
 * Toggles the dropdown specifically for the arrow click
 *
 * Filter logic: Shows matching domains as you type.
 * If searching, it ensures the list is visible.
 */
function filterDomains() {
  const searchVal = document
    .getElementById("domain_search")
    .value.toLowerCase();
  const dropdown = document.getElementById("domain_dropdown");
  const items = document.querySelectorAll(".domain-item");
  const arrow = document.getElementById("dropdown_arrow");

  if (searchVal.length > 0) {
    dropdown.style.display = "block"; // Show to display results
    arrow.classList.replace("bx-chevron-down", "bx-chevron-up");

    items.forEach((item) => {
      const text = item.innerText.toLowerCase();
      item.style.display = text.includes(searchVal) ? "block" : "none";
    });
  } else {
    // If search is cleared, hide the list unless it was manually expanded
    if (!window.dropdownManuallyExpanded) {
      dropdown.style.display = "none";
      arrow.classList.replace("bx-chevron-up", "bx-chevron-down");
    }
  }
}

/**
 * Dropdown Arrow Toggle Logic
 */
function toggleDomainDropdown(event) {
  if (event) event.stopPropagation();
  const dropdown = document.getElementById("domain_dropdown");
  const arrow = document.getElementById("dropdown_arrow");
  const isHidden = dropdown.style.display === "none" || dropdown.style.display === "";

  if (isHidden) {
    dropdown.style.display = "block";
    arrow.style.transform = "rotate(180deg)";
  } else {
    dropdown.style.display = "none";
    arrow.style.transform = "rotate(0deg)";
  }
}

// Add global state tracker
window.dropdownManuallyExpanded = false;

/**
 * Modified update function to handle placeholder behavior
 */
function updateSelectedDomains() {
  const display = document.getElementById("selected_domains_display");
  const searchInput = document.getElementById("domain_search");
  const checkedBoxes = document.querySelectorAll(".domain-selector:checked");

  display.innerHTML = "";

  if (checkedBoxes.length > 0) {
    searchInput.placeholder = ""; // Hide placeholder if domains are selected
    checkedBoxes.forEach((checkbox) => {
      const chip = document.createElement("span");
      chip.className =
        "badge bg-dark bg-opacity-50 border border-white border-opacity-10 rounded-pill d-inline-flex align-items-center px-3 py-1 me-1 mb-1";
      chip.style.fontSize = "11px";
      chip.innerHTML = `
                <span class="text-white opacity-75">${checkbox.value}</span>
                <i class='bx bx-x ms-2' style="cursor:pointer" onclick="removeDomainChip('${checkbox.id}'); event.stopPropagation();"></i>
            `;
      display.appendChild(chip);
    });
  } else {
    searchInput.placeholder = "Click to select domains...";
  }

  // Update domain availability across all selectors
  updateDomainAvailability();
}

/**
 * Remove a domain chip
 * @param {string} checkboxId - ID of checkbox to uncheck
 */
function removeDomainChip(checkboxId) {
  const checkbox = document.getElementById(checkboxId);
  if (checkbox) {
    checkbox.checked = false;
    updateSelectedDomains();
  }
}

/**
 * Select all domains
 */
function selectAllDomains() {
  const checkboxes = document.querySelectorAll(".domain-selector");
  checkboxes.forEach((cb) => (cb.checked = true));
  updateSelectedDomains();
}

/**
 * Toggle domain section visibility based on "Expose to Web" choice
 */
function toggleDomainSection() {
  const isPublic =
    document.getElementById("expose_web_toggle").value === "true";
  const wrapper = document.getElementById("domain_selection_wrapper");

  if (isPublic) {
    wrapper.style.display = "flex";
    wrapper.classList.replace("animate__fadeOut", "animate__fadeIn");
  } else {
    wrapper.classList.replace("animate__fadeIn", "animate__fadeOut");

    // Delay display:none to allow animation
    setTimeout(() => {
      if (document.getElementById("expose_web_toggle").value === "false") {
        wrapper.style.display = "none";
      }
    }, 500);

    // Clear selections when hiding
    document
      .querySelectorAll(".domain-selector")
      .forEach((cb) => (cb.checked = false));
    updateSelectedDomains();
  }
}

/**
 * Update domain availability across all selectors
 * Ensures a domain can only be used in ONE place at a time
 * Uses database-backed DOMAIN_USAGE_MAP for cross-lab checking
 */
function updateDomainAvailability() {
  // 1. Use the database-backed usage map (already includes ALL labs)
  const usageMap = window.DOMAIN_USAGE_MAP || {};

  // Also check currently selected domains in THIS modal (not yet saved to DB)
  const currentSelections = {};

  const vscSelector = document.getElementById("vsc_domain_selector");
  if (vscSelector && vscSelector.offsetParent !== null) {
    const vscDomain = vscSelector.value;
    if (vscDomain && !vscDomain.includes('.tomweb.shop')) {
      currentSelections[vscDomain] = { usage: 'VS Code Web', lab_type: window.LAB_TYPE };
    }
  }

  const minioConsoleSelector = document.getElementById("minio_console_domain");
  if (minioConsoleSelector && minioConsoleSelector.offsetParent !== null) {
    const consoleDomain = minioConsoleSelector.value;
    if (consoleDomain && !consoleDomain.includes('.tomweb.shop')) {
      currentSelections[consoleDomain] = { usage: 'MinIO Console', lab_type: window.LAB_TYPE };
    }
  }

  const minioApiSelector = document.getElementById("minio_api_domain");
  if (minioApiSelector && minioApiSelector.offsetParent !== null) {
    const apiDomain = minioApiSelector.value;
    if (apiDomain && !apiDomain.includes('.tomweb.shop')) {
      currentSelections[apiDomain] = { usage: 'S3 API', lab_type: window.LAB_TYPE };
    }
  }

  const n8nSelector = document.getElementById("n8n_domain_selector");
  if (n8nSelector && n8nSelector.offsetParent !== null) {
    const n8nDomain = n8nSelector.value;
    if (n8nDomain && !n8nDomain.includes('.tomweb.shop')) {
      currentSelections[n8nDomain] = { usage: 'n8n Interface', lab_type: window.LAB_TYPE };
    }
  }

  const checkedDomains = document.querySelectorAll(".domain-selector:checked");
  checkedDomains.forEach(checkbox => {
    currentSelections[checkbox.value] = { usage: 'Public Exposure', lab_type: window.LAB_TYPE };
  });

  // 2. Filter VS Code selector options
  if (vscSelector) {
    Array.from(vscSelector.options).forEach(option => {
      const domain = option.value;
      const originalDomain = domain;

      // Only skip filtering for the currently selected domain in THIS selector
      if (domain === vscSelector.value) {
        option.disabled = false;
        option.textContent = originalDomain;
      } else {
        // Check if used in DB or current modal (for ALL domains including .tomweb.shop)
        const dbUsage = usageMap[domain];
        const currentUsage = currentSelections[domain];

        let usageText = '';
        if (dbUsage && dbUsage.usage !== 'VS Code Web') {
          usageText = ` (Used: ${dbUsage.usage} in ${dbUsage.lab_type} lab)`;
        } else if (currentUsage && currentUsage.usage !== 'VS Code Web') {
          usageText = ` (Used: ${currentUsage.usage})`;
        }

        option.disabled = (usageText !== '');
        option.textContent = originalDomain + usageText;
      }
    });
  }

  // 3. Filter MinIO Console selector options
  if (minioConsoleSelector) {
    Array.from(minioConsoleSelector.options).forEach(option => {
      const domain = option.value;
      const originalDomain = domain;

      if (domain === minioConsoleSelector.value) {
        option.disabled = false;
        option.textContent = originalDomain;
      } else {
        const dbUsage = usageMap[domain];
        const currentUsage = currentSelections[domain];

        let usageText = '';
        if (dbUsage && dbUsage.usage !== 'MinIO Console') {
          usageText = ` (Used: ${dbUsage.usage} in ${dbUsage.lab_type} lab)`;
        } else if (currentUsage && currentUsage.usage !== 'MinIO Console') {
          usageText = ` (Used: ${currentUsage.usage})`;
        }

        option.disabled = (usageText !== '');
        option.textContent = originalDomain + usageText;
      }
    });
  }

  // 4. Filter MinIO API selector options
  if (minioApiSelector) {
    Array.from(minioApiSelector.options).forEach(option => {
      const domain = option.value;
      const originalDomain = domain;

      if (domain === minioApiSelector.value) {
        option.disabled = false;
        option.textContent = originalDomain;
      } else {
        const dbUsage = usageMap[domain];
        const currentUsage = currentSelections[domain];

        let usageText = '';
        if (dbUsage && dbUsage.usage !== 'S3 API') {
          usageText = ` (Used: ${dbUsage.usage} in ${dbUsage.lab_type} lab)`;
        } else if (currentUsage && currentUsage.usage !== 'S3 API') {
          usageText = ` (Used: ${currentUsage.usage})`;
        }

        option.disabled = (usageText !== '');
        option.textContent = originalDomain + usageText;
      }
    });
  }

  // 5. Filter n8n selector options
  if (n8nSelector) {
    Array.from(n8nSelector.options).forEach(option => {
      const domain = option.value;
      const originalDomain = domain;

      if (domain === n8nSelector.value) {
        option.disabled = false;
        option.textContent = originalDomain;
      } else {
        const dbUsage = usageMap[domain];
        const currentUsage = currentSelections[domain];

        let usageText = '';
        if (dbUsage && dbUsage.usage !== 'n8n Interface') {
          usageText = ` (Used: ${dbUsage.usage} in ${dbUsage.lab_type} lab)`;
        } else if (currentUsage && currentUsage.usage !== 'n8n Interface') {
          usageText = ` (Used: ${currentUsage.usage})`;
        }

        option.disabled = (usageText !== '');
        option.textContent = originalDomain + usageText;
      }
    });
  }

  // 6. Filter public exposure domain checkboxes
  const allDomainItems = document.querySelectorAll(".domain-item");
  allDomainItems.forEach(item => {
    const checkbox = item.querySelector(".domain-selector");
    if (!checkbox) return;

    const domain = checkbox.value;
    const isCurrentlyChecked = checkbox.checked;
    const label = item.querySelector(".form-check-label");

    // Determine where the domain is used (check DB first, then current modal)
    const dbUsage = usageMap[domain];
    const currentUsage = currentSelections[domain];

    let usageLabel = '';
    let labInfo = '';
    let isUsedInSelector = false;

    if (dbUsage) {
      usageLabel = `Used: ${dbUsage.usage}`;
      labInfo = dbUsage.lab_type ? ` (${dbUsage.lab_type} lab)` : '';
      isUsedInSelector = true;
    } else if (currentUsage) {
      usageLabel = `Used: ${currentUsage.usage}`;
      isUsedInSelector = true;
    }

    if (isUsedInSelector && !isCurrentlyChecked) {
      // Domain is used elsewhere - show it but disabled with usage label
      item.style.display = '';
      item.style.opacity = '0.5';
      checkbox.disabled = true;

      // Add or update usage badge
      let usageBadge = label.querySelector('.usage-badge');
      if (!usageBadge) {
        usageBadge = document.createElement('span');
        usageBadge.className = 'usage-badge badge bg-info bg-opacity-25 text-info ms-2';
        usageBadge.style.fontSize = '9px';
        usageBadge.style.fontWeight = 'normal';
        label.appendChild(usageBadge);
      }
      usageBadge.textContent = usageLabel + labInfo;
    } else {
      // Domain is available - remove opacity and enable
      item.style.display = '';
      item.style.opacity = '1';
      checkbox.disabled = false;

      // Remove usage badge if it exists
      const existingBadge = label.querySelector('.usage-badge');
      if (existingBadge) {
        existingBadge.remove();
      }
    }
  });
}
/**
 * Professional Launcher
 * Opens VS Code for Essentials, S3 Console for MinIO
 */
function launchService(btn, type) {
  // Start loading
  Dashboard.toggleLoading(btn, true);

  setTimeout(() => {
    if (type === "minio") {
      const minioModal = new coreui.Modal(document.getElementById("minioModal"));
      minioModal.show();
    } else if (type === "n8n") {
      let url = "";
      if (window.LAB_CONFIG && window.LAB_CONFIG.fields) {
        const urlField = window.LAB_CONFIG.fields.find(f => f.label === 'Public URL');
        if (urlField) url = urlField.value;
      }

      if (url) {
        window.open(url, '_blank');
      } else {
        alert("n8n URL not found. Please redeploy.");
      }
    } else {
      const vscModal = new coreui.Modal(document.getElementById("vscModal"));
      vscModal.show();
    }

    // Stop loading after action is triggered so it doesn't spin forever
    Dashboard.toggleLoading(btn, false);
  }, 100); // Small delay to show interaction
}
/* ============================================================================
 * UTILITIES: Clipboard Handling
 * ============================================================================ */
document.addEventListener('DOMContentLoaded', () => {
  // Global handler for .clipboard buttons
  document.body.addEventListener('click', async (e) => {
    const btn = e.target.closest('.clipboard');
    if (!btn) return;

    e.preventDefault();
    const text = btn.getAttribute('data-clipboard-text');
    if (!text) return;

    try {
      // Robust Copy Handler with Fallback
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
      } else {
        // Fallback for non-secure contexts
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.left = "-9999px";
        textArea.style.top = "0";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
      }

      // 1. Visual Feedback on Button
      const originalInner = btn.innerHTML;
      btn.innerHTML = '<i class="bx bx-check text-success"></i>';
      btn.classList.add('active');

      setTimeout(() => {
        btn.innerHTML = originalInner;
        btn.classList.remove('active');
      }, 2000);

      // 2. Premium Toast Notification
      const toastEl = document.getElementById('copyToast');
      if (toastEl) {
        const toast = coreui.Toast.getOrCreateInstance(toastEl);
        const titleEl = document.getElementById('toast-title');
        const msgEl = document.getElementById('toast-message');

        // Extract label from tooltip or use generic
        let label = btn.getAttribute('title') || btn.getAttribute('data-coreui-title') || 'Information';
        label = label.replace('Copy ', ''); // Clean up title

        if (titleEl) titleEl.innerText = 'Copied!';
        if (msgEl) msgEl.innerText = `${label} has been copied into your clipboard`;

        toast.show();
      }

    } catch (err) {
      console.error('Failed to copy: ', err);
    }
  });
});
//  * EVENT LISTENERS
//  * ========================================================================== */

/**
 * Initialize dashboard when DOM is ready
 */
document.addEventListener("DOMContentLoaded", () => {
  Dashboard.init();

  // Close dropdown when clicking outside
  document.addEventListener("click", function (event) {
    const dropdown = document.getElementById("domain_dropdown");
    const display = document.getElementById("selected_domains_display");

    if (dropdown && display && domainDropdownOpen) {
      if (!dropdown.contains(event.target) && !display.contains(event.target)) {
        domainDropdownOpen = false;
        dropdown.style.display = "none";
      }
    }
  });
});

/**
 * Learn AI Application UI Logic
 */
(function () {
    'use strict';

    const LearnApp = {
        isInitialized: false,
        isDragging: false,
        currentResizer: null,
        animationFrame: null,
        initialAnchor: 0,
        initialWidth: 0,

        init: function () {
            if (this.isInitialized) return;
            const appWrapper = document.querySelector('.learn-app-wrapper');
            if (!appWrapper) return;

            this.restorePaneWidths();
            this.adjustVHE(appWrapper);
            this.initResizers();
            this.initAIChat();

            window.addEventListener('resize', () => {
                this.adjustVHE(appWrapper);
            });
            this.isInitialized = true;
        },

        // Save and Restore Pane Widths
        savePaneWidth: function (id, width) {
            localStorage.setItem(`learn-pane-${id}`, width);
            // Also sync to document root for immediate CSS consumption
            document.documentElement.style.setProperty(`--${id}-saved-width`, width + 'px');
        },

        restorePaneWidths: function () {
            const panes = ['outlineSidebar', 'courseSidebar', 'paneAI'];
            panes.forEach(id => {
                const target = document.getElementById(id);
                const savedWidth = localStorage.getItem(`learn-pane-${id}`);
                if (target && savedWidth) {
                    target.style.width = savedWidth + 'px';
                    target.style.flexBasis = savedWidth + 'px';
                    // Update CSS variable as well
                    document.documentElement.style.setProperty(`--${id}-saved-width`, savedWidth + 'px');

                    // Update outline state if applicable
                    if (id === 'outlineSidebar') {
                        const widthNum = parseInt(savedWidth);
                        this.setOutlineState(target, widthNum > 180 ? 'expanded' : 'collapsed');
                    }
                }
            });
        },

        // Draggable Resizers Logic (Hardened with Anchors & Delegation)
        initResizers: function () {
            // Use delegation on document for mousedown to handle dynamic or late-rendered resizers
            document.addEventListener('mousedown', (e) => {
                const resizer = e.target.closest('.pane-resizer');
                if (!resizer) return;

                const targetId = resizer.getAttribute('data-target');
                const target = document.getElementById(targetId);
                if (!target) return;

                this.isDragging = true;
                this.currentResizer = resizer;
                resizer.classList.add('is-dragging');
                document.body.classList.add('resizing-active');
                document.body.style.cursor = 'col-resize';
                document.body.style.userSelect = 'none';

                // Prevent iframes from stealing mouse events
                document.querySelectorAll('iframe').forEach(ifrm => {
                    ifrm.style.pointerEvents = 'none';
                });

                // Disable transitions immediately
                target.style.setProperty('transition', 'none', 'important');

                // Cache initial state for stable calculation
                const rect = target.getBoundingClientRect();
                const direction = resizer.getAttribute('data-direction') || 'left';
                this.initialAnchor = (direction === 'left') ? rect.left : rect.right;
                this.initialWidth = rect.width;
            });

            document.addEventListener('mousemove', (e) => {
                if (!this.isDragging || !this.currentResizer) return;

                // Simple throttling via RAF
                if (this.animationFrame) cancelAnimationFrame(this.animationFrame);

                this.animationFrame = requestAnimationFrame(() => {
                    this.handleResize(e);
                });
            });

            const stopDragging = () => {
                if (this.isDragging) {
                    this.isDragging = false;
                    document.body.classList.remove('resizing-active');
                    document.querySelectorAll('iframe').forEach(ifrm => ifrm.style.pointerEvents = 'all');

                    if (this.currentResizer) {
                        this.currentResizer.classList.remove('is-dragging');
                        const targetId = this.currentResizer.getAttribute('data-target');
                        const target = document.getElementById(targetId);
                        if (target) {
                            target.style.removeProperty('transition');
                            this.savePaneWidth(targetId, target.offsetWidth);
                        }
                    }
                    document.body.style.cursor = 'default';
                    document.body.style.userSelect = 'auto';
                    this.currentResizer = null;
                }
            };

            document.addEventListener('mouseup', stopDragging);
            window.addEventListener('blur', stopDragging);
        },

        handleResize: function (e) {
            if (!this.currentResizer) return;

            const targetId = this.currentResizer.getAttribute('data-target');
            const direction = this.currentResizer.getAttribute('data-direction') || 'left';
            const target = document.getElementById(targetId);

            if (target) {
                let newWidth;

                if (direction === 'left') {
                    // Distance from fixed left edge (anchor) to mouse
                    newWidth = e.clientX - this.initialAnchor;
                } else {
                    // Distance from fixed right edge (anchor) to mouse
                    newWidth = this.initialAnchor - e.clientX;
                }

                // Standardized constraints
                if (targetId === 'outlineSidebar') {
                    newWidth = Math.max(70, Math.min(500, newWidth));
                    const isCollapsed = target.getAttribute('data-state') === 'collapsed';
                    if (newWidth > 180 && isCollapsed) {
                        this.setOutlineState(target, 'expanded');
                    } else if (newWidth <= 180 && !isCollapsed) {
                        this.setOutlineState(target, 'collapsed');
                    }
                } else if (targetId === 'courseSidebar') {
                    newWidth = Math.max(250, Math.min(600, newWidth));
                } else if (targetId === 'paneAI') {
                    newWidth = Math.max(250, Math.min(800, newWidth));
                }

                // Double Apply for flexbox and standard layout
                target.style.width = newWidth + 'px';
                target.style.flexBasis = newWidth + 'px';
                target.style.minWidth = '0px'; // Prevent min-width blocking
                document.documentElement.style.setProperty(`--${targetId}-saved-width`, newWidth + 'px');
            }
        },

        setOutlineState: function (sidebar, state) {
            const compactView = sidebar.querySelector('.outline-compact');
            const fullView = sidebar.querySelector('.outline-full');
            if (!compactView || !fullView) return;

            if (state === 'expanded') {
                sidebar.setAttribute('data-state', 'expanded');
                compactView.classList.add('d-none');
                fullView.classList.remove('d-none');
                fullView.classList.add('d-flex');
            } else {
                sidebar.setAttribute('data-state', 'collapsed');
                fullView.classList.add('d-none');
                fullView.classList.remove('d-flex');
                compactView.classList.remove('d-none');
                compactView.classList.add('d-flex');
            }
        },

        toggleOutline: function () {
            const sidebar = document.getElementById('outlineSidebar');
            if (!sidebar) return;

            const isCollapsed = sidebar.getAttribute('data-state') === 'collapsed';
            const newWidth = isCollapsed ? 300 : 70;

            sidebar.style.width = newWidth + 'px';
            sidebar.style.flexBasis = newWidth + 'px';
            this.setOutlineState(sidebar, isCollapsed ? 'expanded' : 'collapsed');
            this.savePaneWidth('outlineSidebar', newWidth);
        },

        adjustVHE: function (appWrapper) {
            const header = document.querySelector('header.header');
            const footer = document.querySelector('footer.footer');

            if (header && footer) {
                const headerHeight = header.offsetHeight;
                const footerHeight = footer.offsetHeight;
                const headerStyle = window.getComputedStyle(header);
                const headerMB = parseFloat(headerStyle.marginBottom) || 0;

                const availableHeight = window.innerHeight - headerHeight - footerHeight - headerMB;
                document.documentElement.style.setProperty('--app-height', `${availableHeight}px`);

                if (appWrapper.classList.contains('stable-app-view')) {
                    document.body.style.height = '100vh';
                    document.body.style.overflow = 'hidden';
                    const mainWrapper = document.querySelector('.wrapper');
                    if (mainWrapper) {
                        mainWrapper.style.height = '100vh';
                        mainWrapper.style.overflow = 'hidden';
                    }
                } else {
                    document.body.style.height = 'auto';
                    document.body.style.overflow = 'auto';
                    const mainWrapper = document.querySelector('.wrapper');
                    if (mainWrapper) {
                        mainWrapper.style.height = 'auto';
                        mainWrapper.style.overflow = 'visible';
                    }
                }
            }

            let vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        },

        // AI Chat Functionality
        initAIChat: function () {
            const chatInput = document.getElementById('aiChatInput');
            const chatSend = document.getElementById('aiChatSend');
            const chatHistory = document.getElementById('aiChatHistory');
            const chapterId = document.getElementById('currentChapterId')?.value;

            if (!chatInput || !chatSend || !chatHistory) return;

            // Auto-scroll to bottom to show latest server-rendered messages
            setTimeout(() => { chatHistory.scrollTop = chatHistory.scrollHeight; }, 100);

            const userSessionId = 'sess_' + Math.random().toString(36).substr(2, 9);
            const aiSocket = new TomSocketClient();
            let idleTimer = null;

            const handleAIStream = (data) => {
                const aiMsgContainer = document.querySelector('.current-ai-stream');
                if (!aiMsgContainer) return;
                const p = aiMsgContainer.querySelector('p');

                if (data.type === 'stream_end') {
                    // Explicitly remove typing dots if they are still there
                    const dots = aiMsgContainer.querySelector('.typing-dots');
                    if (dots) dots.remove();

                    aiMsgContainer.classList.remove('current-ai-stream');
                    return;
                }

                if (data.type === 'text_delta') {
                    // Remove typing dots if present
                    if (p.querySelector('.typing-dots')) {
                        p.innerHTML = '';
                    }

                    p.innerHTML += data.data;
                    chatHistory.scrollTop = chatHistory.scrollHeight;
                }
            };

            const ensureSocketConnection = () => {
                if (!aiSocket.isActive()) {
                    aiSocket.connect(`ai_stream.${userSessionId}`, handleAIStream);
                }
            };

            const resetIdleTimer = () => {
                ensureSocketConnection();
                clearTimeout(idleTimer);
                idleTimer = setTimeout(() => {
                    console.log('AI Chat idle for 60s... dropping persistent socket to save resources.');
                    aiSocket.disconnect();
                }, 60000); // 60 seconds
            };

            // Connect instantly on load
            resetIdleTimer();

            // Re-establish/refresh connection idleness on typing
            chatInput.addEventListener('input', resetIdleTimer);

            chatSend.addEventListener('click', () => {
                const query = chatInput.value.trim();
                const currentChapterId = document.getElementById('currentChapterId')?.value || '';
                if (!query) return;

                resetIdleTimer(); // ensure socket gets bumped or reconnected on send

                const modelSelect = document.getElementById('aiModelSelect');
                const aiModel = modelSelect ? modelSelect.value : 'gemini';

                const messageId = 'msg_' + Math.random().toString(36).substr(2, 9);
                chatInput.value = '';

                // Add user message
                this.appendChatMessage('User', query, 'user-row ms-auto');

                // Prepare AI message placeholder
                const aiMsgId = 'ai_msg_' + messageId;
                this.appendChatMessage('SathishBot', '<div class="typing-dots"><span></span><span></span><span></span></div>', 'ai-row current-ai-stream', aiMsgId);

                // 2. Trigger AI Generation
                fetch('/src/api/learnAI/ask.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ query, chapter_id: currentChapterId, message_id: messageId, session_id: userSessionId, ai_model: aiModel })
                })
                    .then(res => res.json())
                    .catch(err => {
                        console.error('AI Request Failed:', err);
                        const aiMsgContainer = document.getElementById(aiMsgId);
                        if (aiMsgContainer) aiMsgContainer.querySelector('p').innerText = 'Sorry, something went wrong.';
                    });
            });

            chatInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') chatSend.click();
            });
        },

        appendChatMessage: function (sender, text, type, id = null) {
            const chatHistory = document.getElementById('aiChatHistory');
            if (!chatHistory) return;

            const userAvatar = document.getElementById('userAvatarUrl')?.value || '';
            const userAvatarStyle = document.getElementById('userAvatarStyle')?.value || '';
            const aiAvatar = document.getElementById('aiAvatarUrl')?.value || '/assets/logo/logo.png';

            const msgDiv = document.createElement('div');
            msgDiv.className = `message-row ${type}`;
            if (id) msgDiv.id = id;

            if (type.includes('ai-row')) {
                msgDiv.innerHTML = `
                    <div class="msg-avatar">
                        <img src="${aiAvatar}" alt="AI">
                    </div>
                    <div class="msg-bubble">
                        <p class="m-0">${text}</p>
                    </div>
                `;
            } else {
                msgDiv.innerHTML = `
                    <div class="msg-bubble">
                        <p class="m-0">${text}</p>
                    </div>
                    <div class="msg-avatar shadow-sm border border-secondary border-opacity-25">
                        <img src="${userAvatar}" style="${userAvatarStyle}" alt="User">
                    </div>
                `;
            }

            chatHistory.appendChild(msgDiv);
            chatHistory.scrollTop = chatHistory.scrollHeight;
        }
    };

    // Initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => LearnApp.init());
    } else {
        LearnApp.init();
    }

    // Export toggle for button clicks
    window.toggleOutline = () => LearnApp.toggleOutline();

    // Re-check for resizers after a small delay in case of late rendering
    setTimeout(() => { if (!LearnApp.isInitialized) LearnApp.init(); }, 1000);
})();

/**
 * ============================================================================
 * TOM LABS - MESSAGE QUEUE CLIENT (MQ)
 * ============================================================================
 * Handles all WebSocket connections, STOMP messaging, and real-time updates
 *
 * Dependencies: stomp.js
 * Used by: lab-dashboard.js
 *
 * @version 1.0.0
 * @author Sathish - Tom Labs
 * ============================================================================
 */

/**
 * Hardened Socket Factory with Subscription Tracking and Delayed Binding
 */
const TomSocketClient = function () {
  this.client = null;
  this.isConnected = false;
  this.isSubscribed = false;
  this.subRetryCount = 0;

  /**
   * Establish WebSocket connection to RabbitMQ
   * @param {string} exchange - RabbitMQ exchange name
   * @param {function} callback - Message handler function
   * @param {object} ui - UI elements (optional, e.g., status dot)
   */
  this.connect = function (exchange, callback, ui = null) {
    try {
      const ws = new WebSocket("wss://mq.awshosting.in/ws");
      this.client = Stomp.over(ws);
      this.client.debug = null; // Set to console.log for debugging

      this.client.connect(
        "admin",
        "RootTom@46",
        () => {
          this.isConnected = true;
          if (ui && ui.dot) ui.dot.style.color = "#a6e3a1"; // Green

          console.log(`[✓] Socket connected to exchange: ${exchange}`);

          // Start the subscription attempt
          this.safeSubscribe(exchange, callback, ui);
        },
        (err) => {
          this.isConnected = false;
          this.isSubscribed = false;
          if (ui && ui.dot) ui.dot.style.color = "#f38ba8"; // Red

          console.warn("Socket connection failed, retrying in 2s...", err);
          setTimeout(() => this.connect(exchange, callback, ui), 2000);
        },
        "/",
      );
    } catch (e) {
      console.error("Socket Error:", e);
    }
  };

  /**
   * Gracefully disconnect the WebSocket
   */
  this.disconnect = function () {
    if (this.client && this.isConnected) {
      this.client.disconnect(() => {
        console.log("Socket gracefully disconnected to save resources.");
      });
      this.isConnected = false;
      this.isSubscribed = false;
    }
  };

  /**
   * Attempts to subscribe to an exchange, with retries if the exchange isn't ready
   * @param {string} exchange - Exchange name
   * @param {function} callback - Message handler
   * @param {object} ui - UI elements
   */
  this.safeSubscribe = function (exchange, callback, ui) {
    // Wait 1 second initially to give the worker time to declare the exchange
    setTimeout(() => {
      if (!this.isConnected) return;

      try {
        // Use /topic/ instead of /exchange/ for better stability and resource management
        // This maps to amq.topic exchange in RabbitMQ
        let destination;
        if (exchange.startsWith('logs.') || exchange.startsWith('ai_stream.')) {
          destination = `/topic/${exchange}`;
        } else {
          destination = `/exchange/${exchange}/#`;
        }

        this.client.subscribe(destination, (m) => {
          if (m.body) {
            try {
              callback(JSON.parse(m.body));
            } catch (e) {
              callback(m.body);
            }
          }
        });
        this.isSubscribed = true;
        this.subRetryCount = 0;
        console.log(`[✓] Subscribed to ${destination}`);
      } catch (e) {
        console.warn(
          `[!] Subscription failed for ${exchange}. Worker might not be ready. Retrying...`,
        );
        if (this.subRetryCount < 10) {
          this.subRetryCount++;
          this.safeSubscribe(exchange, callback, ui);
        }
      }
    }, 1000);
  };

  /**
   * Check if socket is currently connected
   * @returns {boolean}
   */
  this.isActive = function () {
    return this.isConnected && this.isSubscribed;
  };

  /**
   * Reconnect to the exchange
   * @param {string} exchange
   * @param {function} callback
   * @param {object} ui
   */
  this.reconnect = function (exchange, callback, ui = null) {
    if (this.isConnected) {
      console.log("Socket already connected. Skipping reconnection.");
      return;
    }
    this.connect(exchange, callback, ui);
  };
};

/**
 * Global Socket Instances
 * - OverviewSocket: System-wide stats (CPU, RAM, etc.)
 * - LogSocket: Instance-specific deployment logs
 */
const OverviewSocket = new TomSocketClient();
const LogSocket = new TomSocketClient();

/**
 * ============================================================================
 * ACTIVITY TRACKER - Resource Optimization
 * ============================================================================
 * Disconnects sockets when user is idle or tab is hidden
 */
const ActivityTracker = {
  idleTimer: null,
  idleTimeout: 300000, // 5 Minutes (in milliseconds)
  isProcessing: false, // Flag to prevent disconnection during critical operations

  /**
   * Initialize activity tracking
   */
  init: function () {
    // 1. Listen for Tab Switching
    document.addEventListener("visibilitychange", () => {
      if (document.hidden) {
        console.log("Tab hidden: Optimization active.");
        this.stopAllSockets();
      } else {
        console.log("Tab focused: Reconnecting...");
        this.reconnectSockets();
      }
    });

    // 2. Listen for Mouse/Keyboard Activity
    const resetTimer = () => {
      clearTimeout(this.idleTimer);
      this.idleTimer = setTimeout(() => {
        console.warn("User idle: Disconnecting sockets.");
        this.stopAllSockets();
      }, this.idleTimeout);
    };

    window.onload = resetTimer;
    document.onmousemove = resetTimer;
    document.onkeypress = resetTimer;
    document.onscroll = resetTimer;
    document.onclick = resetTimer;
  },

  /**
   * Stop all socket connections (called when idle or tab hidden)
   */
  stopAllSockets: function () {
    // PREVENT: If we are deploying or stopping, DO NOT close the socket
    if (this.isProcessing) {
      console.log(
        "[Optimization Bypass] System is busy with logs. Keeping socket alive.",
      );
      return;
    }

    OverviewSocket.disconnect();
    LogSocket.disconnect();

    console.log("[Optimization] All sockets disconnected to save resources.");
  },

  /**
   * Reconnect sockets when user returns
   */
  reconnectSockets: function () {
    // This will be called by the Dashboard to re-init sockets
    if (
      typeof Dashboard !== "undefined" &&
      typeof Dashboard.initSockets === "function"
    ) {
      Dashboard.initSockets();
    }
  },

  /**
   * Set processing flag to prevent socket closure during critical operations
   * @param {boolean} processing
   */
  setProcessing: function (processing) {
    this.isProcessing = processing;
    console.log(
      `[ActivityTracker] Processing mode: ${processing ? "ON" : "OFF"}`,
    );
  },
};

/**
 * ============================================================================
 * MQ UTILITIES - Helper Functions
 * ============================================================================
 */
const MQUtils = {
  /**
   * Update MQ status indicator in UI
   * @param {string} status - 'connected', 'disconnected', 'connecting'
   */
  updateStatusIndicator: function (status) {
    const dot = document.getElementById("mq-status-dot");
    if (!dot) return;

    const colors = {
      connected: "#a6e3a1", // Green
      disconnected: "#f38ba8", // Red
      connecting: "#f9e2af", // Yellow
    };

    dot.style.color = colors[status] || colors.disconnected;
  },

  /**
   * Log MQ events to console (debug mode)
   * @param {string} message
   * @param {string} type - 'info', 'warn', 'error'
   */
  log: function (message, type = "info") {
    const prefix = "[MQ]";
    switch (type) {
      case "warn":
        console.warn(prefix, message);
        break;
      case "error":
        console.error(prefix, message);
        break;
      default:
        console.log(prefix, message);
    }
  },

  /**
   * Parse incoming message (handle both JSON and plain text)
   * @param {*} data
   * @returns {object|string}
   */
  parseMessage: function (data) {
    if (typeof data === "string") {
      try {
        return JSON.parse(data);
      } catch (e) {
        return data;
      }
    }
    return data;
  },
};

/**
 * Export for use in other modules (if using ES6 modules)
 * For legacy script tags, these are already global
 */
if (typeof module !== "undefined" && module.exports) {
  module.exports = {
    TomSocketClient,
    OverviewSocket,
    LogSocket,
    ActivityTracker,
    MQUtils,
  };
}

/**
 * macOS Style Background Parallax Initializer
 */
const TomParallax = {
  instance: null,
  sceneElement: null,

  init: function () {
    this.sceneElement = document.getElementById("scene");
    if (!this.sceneElement) return;

    // Initialize the engine
    this.instance = new Parallax(this.sceneElement, {
      relativeInput: true, // Movement relative to the element
      hoverOnly: false, // Track across the whole viewport like reference
      pointerEvents: false,
      frictionX: 1.0, // Instant movement matching SN
      frictionY: 1.0,
      scalarX: 2.0, // Subtle movement range matching SN
      scalarY: 2.0,
      invertX: true, // Opposite direction tracking
      invertY: true,
      originX: 0.5,
      originY: 0.5,
    });

    console.log("[✓] Parallax background active.");
  },

  // Optimization: Stop movement if user is idle or tab is hidden
  destroy: function () {
    if (this.instance) {
      this.instance.disable();
      console.log("[Optimization] Parallax suspended.");
    }
  },

  resume: function () {
    if (this.instance) {
      this.instance.enable();
    }
  },
};

// Start Parallax when DOM is ready
document.addEventListener("DOMContentLoaded", () => TomParallax.init());

/**
 * DEPRECATED - LOGIC MOVED TO PHP TEMPLATES
 * This file is empty to prevent conflicts with local template scripts.
 */

/**
 * DEPRECATED - LOGIC MOVED TO PHP TEMPLATES
 * This file is empty to prevent conflicts with local template scripts.
 */
