# Prod Migration – Step 1 Automation (SSH tunnel)

This folder contains a minimal Ansible project that automates **Step 1** of the
prod migration runbook: establishing an SSH session to the new backend server
through the bastion/SSH tunnel. Only documentation and helper assets live here;
no secrets or live inventories should be committed.

## Layout

- `ansible.cfg` – pins inventory path and disables noisy Ansible artifacts.
- `inventory/hosts.ini` – minimal inventory that only lists the `ovh_backend`
  group; all host details/credentials are sourced from group vars.
- `group_vars/ovh_backend.yml` – shared defaults for hosts in the
  `ovh_backend` group.
- `group_vars/ovh_backend_vault.yml` – vaulted secrets (bastion/ backend host
  data and passwords). Copy from `.example` and encrypt before use.
- `roles/ssh_tunnel/` – idempotent tasks that create the control master
  directory, open the SSH tunnel, verify the port, and emit the endpoint.
- `playbooks/step1_ssh_tunnel.yml` – entry point used to run the role locally.

## Prerequisites

1. Install Ansible >= 2.13 on the operator workstation.
2. Install [`sshpass`](https://linux.die.net/man/1/sshpass) so the tunnel role can
   feed the backend password to `ssh` when needed.
3. Provide the backend login password via `backend_password` (keep it inside
   `group_vars/ovh_backend_vault.yml`, which can be encrypted with
   `ansible-vault`).
4. Ensure the SSH private key (`ssh_private_key` variable) has access to the
   bastion/new-backend host. Keep it outside the repo and reference it via an
   absolute path.
5. Update `inventory/hosts.ini` (or a copy) with the actual hostnames, ports,
   and private IP of the backend service.
6. Confirm the bastion can reach the backend host (security groups + routing).

## Running the playbook

```bash
cd docs/modules/tasks/prod-migration/ansible
ansible-playbook -i inventory/hosts.ini playbooks/step1_ssh_tunnel.yml
```

Pass overrides with `-e` or use host/group vars files if you manage multiple
stacks. When the playbook completes it prints the tunnel endpoint so that the
following migration steps (DB dump, runtime-data sync, etc.) can re-use the
forwarded port `localhost:local_tunnel_port`.

## Next steps

With an active tunnel you can continue executing the remaining prod migration
steps (db dumps, archives, transfers) by running additional playbooks or manual
commands targeting the forwarded port. Each follow-up task should re-use the
`local_tunnel_port` defined here to avoid duplicating SSH connectivity logic.
Store secrets (e.g. `backend_password`, `bastion_host`, `ssh_private_key`) in
`group_vars/ovh_backend_vault.yml` and protect the file with
`ansible-vault encrypt group_vars/ovh_backend_vault.yml` whenever possible.
