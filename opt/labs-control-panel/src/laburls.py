"""Public URL construction for lab instances.

Mirrors the PHP ``LabUrl`` class so both sides of the platform agree:

* **Host** comes from the instance document (``code_domain`` / ``gui_domain``)
  — the very value the routing layer (Traefik) generates its ``Host(...)``
  rules from. When the user attaches a custom domain, that domain is stored in
  the same field, so the URL always points at the host that is really served.
* **Scheme** comes from the deployment configuration (env var
  ``CODE_URL_SCHEME`` > ``code_url_scheme`` in env.json). A local dev box can
  therefore use ``http`` while production stays ``https`` — one key, no code
  change, no stale values baked into the database.

These helpers are the single place where the two languages agree; the
deploy paths (``commands/lab.py`` and ``Lab.py``) both call them so the stored
credentials and the generated routing can never drift apart.
"""


def code_domain(cfg, instance_id, lab_data=None, override=None):
    """Hostname of the Code IDE service (code-server) for an instance."""
    lab_data = lab_data or {}
    if override:
        return override
    chosen = (lab_data.get("code_domain") or "").strip()
    if chosen:
        return chosen
    return f"code-{instance_id}.{cfg.instance_domain_zone}"


def gui_domain(cfg, instance_id, lab_data=None, override=None):
    """Hostname of the GUI (VNC) service for an instance."""
    lab_data = lab_data or {}
    if override:
        return override
    chosen = (lab_data.get("gui_domain") or "").strip()
    if chosen:
        return chosen
    return f"gui-{instance_id}.{cfg.instance_domain_zone}"


def service_domain(cfg, instance_id, prefix):
    """Hostname of an auxiliary service (n8n-, s3-, api-)."""
    return f"{prefix}{instance_id}.{cfg.instance_domain_zone}"


def url(cfg, host):
    """Absolute URL for a host using the configured scheme."""
    host = (host or "").strip()
    if not host:
        return ""
    scheme = cfg.code_url_scheme
    if host.startswith("http://") or host.startswith("https://"):
        return f"{scheme}://" + host.split("://", 1)[1]
    return f"{scheme}://{host}"
