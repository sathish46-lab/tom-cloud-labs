"""
MCP Ownership Resolver
Maps (user, lab_name) → instance_hash safely, verifying ownership.
"""

import hashlib
import logging
from typing import Dict, Any, Optional

from src.config import Config

logger = logging.getLogger(__name__)

# Same salt used in PHP User::getLabHash()
LAB_HASH_SALT = "8b51626f3a468904e8b6f83747f2fcf1"


class OwnershipError(Exception):
    """Ownership verification error."""
    pass


class OwnershipResolver:
    """Resolves lab identifiers to instance hashes, verifying ownership."""

    def __init__(self, mongo_client=None):
        self.config = Config()
        if mongo_client:
            self.db = mongo_client[self.config.main_db]
        else:
            import pymongo
            self.db = pymongo.MongoClient(self.config.mongo_uri)[self.config.main_db]

    def get_lab_hash(self, email: str, lab_name: str) -> str:
        """Generate the deterministic instance hash for a user's lab."""
        if not email:
            return hashlib.md5(("guest" + lab_name + LAB_HASH_SALT).encode()).hexdigest()
        return hashlib.md5((email + lab_name + LAB_HASH_SALT).encode()).hexdigest()

    def resolve_lab(
        self,
        user: Dict[str, Any],
        lab_name: Optional[str] = None,
        instance_hash: Optional[str] = None
    ) -> str:
        """
        Resolve a lab identifier to instance_hash, verifying ownership.

        Args:
            user: User dict with user_id, username, email
            lab_name: Lab name (e.g., "essentials")
            instance_hash: Direct instance hash

        Returns:
            Verified instance_hash

        Raises:
            OwnershipError: If lab not found or access denied
        """
        if lab_name:
            value = lab_name.strip()
        elif instance_hash:
            value = instance_hash.strip()
        else:
            raise OwnershipError("Either lab_name or instance_hash must be provided")

        # 1) exact instance_hash match (must belong to this user)
        doc = self.db.machine_labs.find_one({
            "instance_hash": value,
            "user_id": user["user_id"]
        })
        if doc:
            return value

        # 2) derive hash from the lab name
        expected_hash = self.get_lab_hash(user["email"], value)
        doc = self.db.machine_labs.find_one({
            "instance_hash": expected_hash,
            "user_id": user["user_id"]
        })
        if doc:
            return expected_hash

        # 3) not found — tell the caller what IS available so agents can self-correct
        labs = self.list_user_labs(user)
        available = ", ".join(
            f"{l.get('instance_hash')}" + (f" ({l.get('lab_name')})" if l.get("lab_name") else "")
            for l in labs
        ) or "none"
        raise OwnershipError(
            f"Lab '{value}' not found for your account. "
            f"Available labs: {available} — pass instance_hash or exact lab name"
        )

    def _user_identifiers(self, user: Dict[str, Any]) -> list:
        """Every value machine_labs may store as user_id for this user.

        Historic docs store numeric ids, ObjectId strings (PHP deploy)
        or usernames; $or across all of them so no lab is skipped.
        """
        ids: list = []
        for key in ("user_id", "_id", "email", "username"):
            if user.get(key) is None:
                continue
            value = user[key]
            ids.append(value)
            if not isinstance(value, str):
                ids.append(str(value))
            try:
                ids.append(int(value))
            except (TypeError, ValueError):
                pass
        # deduplicate, keep order
        seen = set()
        return [v for v in ids if v is not None and not (v in seen or seen.add(v))]

    def list_user_labs(self, user: Dict[str, Any]) -> list:
        """List all labs owned by the user, stopped ones included.

        Full projection on purpose: tools build rich dashboard-style output
        (dashboard parity) from the control-plane record, never from a live
        container scan.
        """
        return list(self.db.machine_labs.find(
            {"user_id": {"$in": self._user_identifiers(user)}},
            {
                "instance_hash": 1, "lab_type": 1, "status": 1, "created_at": 1,
                "internal_ip": 1, "docker_ip": 1, "tunnel_ip": 1,
                "domains": 1, "http_proxies": 1, "expose_web": 1,
                "code_domain": 1, "gui_domain": 1, "email": 1, "username": 1,
            }
        ))

    def get_lab_info(self, user: Dict[str, Any], instance_hash: str) -> Optional[dict]:
        """Get full lab info for a verified instance."""
        doc = self.db.machine_labs.find_one({
            "instance_hash": instance_hash,
            "user_id": {"$in": self._user_identifiers(user)}
        })
        return doc


# Global ownership resolver instance
_ownership_instance = None


def get_ownership() -> OwnershipResolver:
    """Get or create the global ownership resolver instance."""
    global _ownership_instance
    if _ownership_instance is None:
        _ownership_instance = OwnershipResolver()
    return _ownership_instance