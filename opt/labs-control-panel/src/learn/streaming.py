"""Shared stream-chunk helpers."""


def chunk_text(chunk):
    """Text of a stream chunk; '' for metadata-only chunks.

    google-generativeai raises ValueError on ``.text`` when a chunk carries a
    finish_reason but an empty parts list — a known intermittent gRPC tail-chunk
    behavior that aborted chapter/roadmap/chat generations mid-stream.
    """
    try:
        return chunk.text or ''
    except ValueError:
        return ''
