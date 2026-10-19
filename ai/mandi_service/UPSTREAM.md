# Upstream

This folder is a **snapshot copy** of the Mandi Setu price service.

- Source: https://github.com/Valkner7/mandi-price-forecast (branch `main`)
- Snapshot commit: `f48b1e2`
- Taken: 2026-10-18
- Data files (`clean_mandi_prices.csv`, `quarantined_rows.csv`) refreshed on 2026-10-19 from source commit `60a2ff0`; the code is unchanged.
- Left out: `.git`, `.github` (its workflows run in the source repo, not here) and `frontend/` (React dashboard source; the built dashboard in `static/dashboard` is kept so the service starts normally).

The source repo is the one that fetches new prices every day and retrains the model. Do not edit files here: make changes in the source repo, then refresh this copy with:

```powershell
powershell -ExecutionPolicy Bypass -File tools\sync-mandi-service.ps1
```

The script only reads from the source repo. It never commits or pushes there.
