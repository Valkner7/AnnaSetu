"""Checks for tools/mandi_data_gap_report.py. Run:  python tests/check_mandi_data_gap.py
Exit code 0 = all checks passed, 1 = at least one failure. No network needed."""
import sys
from datetime import date, timedelta
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent / "tools"))
import mandi_data_gap_report as g

failures = []


def check(label, ok, detail=""):
    print(f"  {'ok  ' if ok else 'FAIL'} {label}" + (f"  [{detail}]" if detail and not ok else ""))
    if not ok:
        failures.append(label)


d0 = date(2026, 10, 1)
full = {d0 + timedelta(days=i) for i in range(10)}          # 1..10 Oct, no gaps

print("[1] no gaps")
r = g.build_report(full, window=10)
check("coverage is 1.0", r["coverage"] == 1.0, r)
check("no export range", r["export_range"] is None and r["missing_ranges"] == [], r)

print("[2] gaps are grouped into runs")
days = full - {date(2026, 10, 3), date(2026, 10, 4), date(2026, 10, 7)}
r = g.build_report(days, window=10)
check("two runs", r["missing_ranges"] == [["2026-10-03", "2026-10-04"], ["2026-10-07", "2026-10-07"]], r)
check("3 missing, 7 present", r["days_missing"] == 3 and r["days_with_data"] == 7, r)
check("export range spans first to last gap", r["export_range"] == ["2026-10-03", "2026-10-07"], r)

print("[3] today later than the data counts the trailing days as missing")
r = g.build_report(full, window=5, today=date(2026, 10, 13))
check("11 to 13 Oct missing", r["missing_ranges"] == [["2026-10-11", "2026-10-13"]], r)

print("[4] a trailing gap is closed out")
r = g.build_report({date(2026, 10, 1)}, window=3, today=date(2026, 10, 3))
check("2 to 3 Oct missing", r["missing_ranges"] == [["2026-10-02", "2026-10-03"]], r)

print("[5] --check exit code and real file")
import tempfile, pandas as pd, os
with tempfile.TemporaryDirectory() as t:
    path = os.path.join(t, "x.csv")
    pd.DataFrame({"date": [date.today().isoformat()]}).to_csv(path, index=False)
    check("low coverage -> exit 1", g.main(["--csv", path, "--check"]) == 1)
    check("without --check -> exit 0", g.main(["--csv", path]) == 0)

print("[6] bundled dataset loads")
check("bundled CSV has days", len(g.load_days()) > 100)

print("\nFAILED: " + ", ".join(failures) if failures else "\nAll checks passed.")
sys.exit(1 if failures else 0)
