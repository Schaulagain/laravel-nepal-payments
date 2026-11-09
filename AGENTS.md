## Working here

- Every gateway result goes through `app/Payments/PaymentProcessor.php`, which locks the payment row and applies the result once. Gateways only check and report. See [docs/adr/0001](docs/adr/0001-only-the-gateway-confirms-a-payment.md).
- Amounts are integers in paisa everywhere, and become rupees only for display and inside the gateway classes.
- A change to `app/Payments/Jose/JoseEnvelope.php` needs re-checking against an independent JOSE implementation. See [docs/adr/0002](docs/adr/0002-jose-with-openssl.md).
- Gateway HTTP calls are faked in `tests/Feature/`; the HBL tests play the bank's side with their own keys. Run `php artisan test`.

## Agent skills

### Issue tracker

Issues live in this repo's GitHub Issues, managed with the `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels

The five default labels: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `GLOSSARY.md` and `docs/adr/` at the repo root. See `docs/agents/domain.md`.
