#!/usr/bin/env python3
import sys
from typing import Iterable, Optional, Set, Union

class Arguments:
    def __init__(self, argv: Optional[Iterable[str]] = None):
        if argv is None:
            argv = sys.argv
        self.argv = list(argv)

    def hasFlag(self, name: str) -> bool:
        """Checks if a flag like --help or -h exists anywhere in argv"""
        return any(arg in [f"--{name}", f"-{name[0]}"] for arg in self.argv)

    def getParam(self, n: int) -> Optional[str]:
        idx = 1 + n 
        return self.argv[idx] if idx < len(self.argv) else None

    def getFlagValue(self, name: str) -> Optional[str]:
        """Gets value from --name=value"""
        prefix = f"--{name}="
        for a in self.argv:
            if a.startswith(prefix):
                return a[len(prefix):]
        return None