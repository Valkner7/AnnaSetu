# Upstream

This folder is a **snapshot copy** of the Mandi Setu price service.

- Source: https://github.com/Valkner7/mandi-price-forecast (branch `main`)
- Snapshot commit: `f48b1e2`
- Taken: 2026-10-18
- Data files (`clean_mandi_prices.csv`, `quarantined_rows.csv`) refreshed on 2026-10-19 from source commit `60a2ff0`; the code is unchanged.
- `train_forecast_model.py` and `test_training_gate.py` come from the source repo's branch `fix/training-quality-gate` (commit `7211c40`), which refuses to save a model that does not beat "price stays the same". **If that branch is not yet merged into the source `main`, running `tools/sync-mandi-service.ps1` replaces them with the older ungated versions.** Merge it there first.
- Left out: `.git`, `.github` (its workflows run in the source repo, not here) and `frontend/` (React dashboard source; the built dashboard in `static/dashboard` is kept so the service starts normally).

The source repo is the one that fetches new prices every day and retrains the model. Do not edit files here: make changes in the source repo, then refresh this copy with:

```powershell
powershell -ExecutionPolicy Bypass -File tools\sync-mandi-service.ps1
```

The script only reads from the source repo. It never commits or pushes there.

- `api_secret.py`, `app.py`, `routers/alerts.py`, `render.yaml`, `README.md`, `test_api_secret.py` and `test_question_limit.py` come from the source repo's branch `fix/fail-closed-secrets` (commit `bf32db6`): with no secret set, `/check-alerts` answers 503 and the advisory endpoints answer only callers on the same machine. **If that branch is not yet merged into the source `main`, running `tools/sync-mandi-service.ps1` replaces them with the older open-by-default versions.**
- `app.py`, `requirements-slim.txt`, `test_slim_mode.py` and `README.md` also carry the opt-in `MANDI_SLIM=1` mode from the source repo's branch `fix/slim-mode`. AnnaSetu's start scripts turn it on. **If that branch is not yet merged into the source `main`, running `tools/sync-mandi-service.ps1` replaces them with versions that have no slim mode, and the start scripts (which install `requirements-slim.txt`) would then break.** Merge it there first.
