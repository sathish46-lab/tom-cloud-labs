"""Learn AI generation package (labsctl).

Owns prompt loading + Gemini generation for lessons, chapters and roadmaps.
Import submodules explicitly (e.g. ``from src.learn import curriculum``) —
this package init stays light so plain ``labsctl`` commands never pull in
the google.generativeai stack.
"""
