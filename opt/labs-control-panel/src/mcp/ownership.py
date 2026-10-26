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

    def list_user_labs(self, user: Dict[str, Any]) -> list:
        """List all labs owned by the user."""
        cursor = self.db.machine_labs.find(
            {"user_id": user["user_id"]},
            {"instance_hash": 1, "lab_name": 1, "lab_type": 1, "status": 1, "created_at": 1}
        )
        return list(cursor)

    def get_lab_info(self, user: Dict[str, Any], instance_hash: str) -> Optional[dict]:
        """Get full lab info for a verified instance."""
        doc = self.db.machine_labs.find_one({
            "instance_hash": instance_hash,
            "user_id": user["user_id"]
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