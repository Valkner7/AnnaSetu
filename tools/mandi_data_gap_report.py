"""
tools/mandi_data_gap_report.py

Reports which calendar days are missing from clean_mandi_prices.csv and tells
you exactly which date range to export from Agmarknet to fill them.

WHY THIS EXISTS
The live data.gov.in feed (fetch_daily_mandi_data.py) only serves the CURRENT
day, so a day that was not fetched cannot be recovered from it. The manual
Agmarknet export (https://agmarknet.gov.in, "Daily Price Arrival Report")
accepts a date range, and update_mandi_prices.py merges it in. This tool
measures the gap so the export range is known, not guessed.

USAGE
    python tools/mandi_data_gap_report.py                  # report, exit 0
    python tools/mandi_data_gap_report.py --check          # exit 1 if recent coverage is low
    python tools/mandi_data_gap_report.py --window 60 --min-coverage 0.9 --check
    python tools/mandi_data_gap_report.py --json           # machine-readable output

HOW TO FILL A GAP (the report prints the dates to use)
    1. On agmarknet.gov.in choose Daily Price Arrival Report, State = Punjab,
       and the "export range" the report prints. Download the CSV.
    2. cd ai/mandi_service && python update_mandi_prices.py path/to/export.csv
    3. python train_forecast_model.py     (retrain once the gap is filled)
(This tool lives in tools/, not ai/mandi_service/, because the sync script
replaces that whole folder.)
Re-running step 2 on the same file is safe; duplicates are dropped.
"""

import argparse
import json
import sys
from datetime import date, timedelta
from pathlib import Path

import pandas as pd

SERVICE_DIR = Path(__file__).resolve().parent.parent / "ai" / "mandi_service"
CLEAN_PATH = SERVICE_DIR / "clean_mandi_prices.csv"


def load_days(path: Path = CLEAN_PATH) -> set:
    """Distinct calendar days that have at least one price row."""
    df = pd.read_csv(path, usecols=["date"], parse_dates=["date"])
    return set(df["date"].dt.date.unique())


def missing_ranges(days: set, start: date, end: date) -> list:
    """Missing days between start and end (inclusive), as (first, last) runs."""
    runs, run_start, prev = [], None, None
    d = start
    while d <= end:
        if d not in days:
            if run_start is None:
                run_start = d
            prev = d
        elif run_start is not None:
            runs.append((run_start, prev))
            run_start = None
        d += timedelta(days=1)
    if run_start is not None:
        runs.append((run_start, prev))
    return runs


def build_report(days: set, window: int = 30, today: date | None = None) -> dict:
    if not days:
        raise ValueError("no dated rows in the dataset")
    latest = max(days)
    end = max(latest, today) if today else latest
    start = end - timedelta(days=window - 1)
    covered = sum(1 for i in range(window) if (start + timedelta(days=i)) in days)
    runs = missing_ranges(days, start, end)
    missing = sum((b - a).days + 1 for a, b in runs)
    return {
        "latest_date": latest.isoformat(),
        "window_days": window,
        "window_start": start.isoformat(),
        "window_end": end.isoformat(),
        "days_with_data": covered,
        "days_missing": missing,
        "coverage": round(covered / window, 3),
        "missing_ranges": [[a.isoformat(), b.isoformat()] for a, b in runs],
        # One export covering first-missing..last-missing fills every gap
        # (days already present are deduplicated on import).
        "export_range": ([runs[0][0].isoformat(), runs[-1][1].isoformat()] if runs else None),
    }


def format_report(r: dict) -> str:
    lines = [
        f"Newest data: {r['latest_date']}",
        f"Last {r['window_days']} days ({r['window_start']} to {r['window_end']}): "
        f"{r['days_with_data']} with data, {r['days_missing']} missing "
        f"({r['coverage']:.0%} coverage)",
    ]
    if r["missing_ranges"]:
        lines.append("Missing runs:")
        for a, b in r["missing_ranges"]:
            lines.append(f"  {a}" if a == b else f"  {a} to {b}")
        a, b = r["export_range"]
        lines.append(f"Export range to request from Agmarknet (Punjab): {a} to {b}")
    else:
        lines.append("No gaps in this window.")
    return "\n".join(lines)


def main(argv=None) -> int:
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("--csv", type=Path, default=CLEAN_PATH)
    p.add_argument("--window", type=int, default=30, help="days to inspect, ending at today or the newest data")
    p.add_argument("--min-coverage", type=float, default=0.8)
    p.add_argument("--check", action="store_true", help="exit 1 when coverage is below --min-coverage")
    p.add_argument("--json", action="store_true")
    args = p.parse_args(argv)

    report = build_report(load_days(args.csv), args.window, today=date.today())
    print(json.dumps(report, indent=2) if args.json else format_report(report))
    if args.check and report["coverage"] < args.min_coverage:
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
