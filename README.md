# Ask Tut for Moodle (local_asktut)

An AI teaching assistant for Moodle by Oizn Systems Inc. (https://asktut.com). Teachers and admins ask in plain language and
Ask Tut answers from live Moodle data **as the signed-in person** (Moodle web services with that person's own token, so
Moodle's roles and capabilities apply), asks before any change (Approve / Deny), keeps a change history, and never sends e-mail.

## Requirements
- Moodle 4.5 LTS (tested). Web services + REST protocol enabled (the installer page explains).
- The **Ask Tut service**, deployed into your own Azure subscription (Oizn's install script). The plugin talks only to that service.
- An AI account: Claude (Anthropic API, or Claude on Microsoft Foundry) — or another provider chosen in Tut Admin.
- The Ask Tut service is a commercial product: https://asktut.com/pricing

## Install
1. Install this plugin (Site administration → Plugins → Install plugins, or unzip to `local/asktut`).
2. Site administration → Plugins → Local plugins → Ask Tut: enter the Ask Tut service URL.
3. Tut Admin: choose who can use Tut (students are off by default), what it may read and change, limits, alerts.

## Privacy
Implements the Moodle Privacy API: Tut's usage log (question text optional), change history and ratings are stored in this
Moodle site per user and can be exported or deleted. Questions are sent to your Ask Tut service and to the AI provider you
choose (declared as external locations). Data is not used to train AI models.

## Support
support@oizn.com · Issues: this repository's Issues tab.

## Licence
GNU GPL v3 or later (see LICENSE). © 2026 Oizn Systems Inc.
