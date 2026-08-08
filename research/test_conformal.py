"""Run with: python3 test_conformal.py  (no test framework required)."""
from conformal import calibrate, minimum_calibration_size, label_leakage, realized_risk

def check(name, cond):
    print(("  PASS  " if cond else "  FAIL  ") + name)
    return cond

ok = True
ok &= check("minimum size matches 1/alpha - 1", minimum_calibration_size(0.05) == 19 and minimum_calibration_size(0.5) == 1)

rows = [(0.01 * i, True) for i in range(1, 20)] + [(0.95, False)]
c = calibrate(rows, 0.10)
ok &= check("largest threshold satisfying the bound is chosen", c.threshold == 0.95 and c.false_acceptance_rate == 0.05)

ok &= check("small group is not certifiable", not calibrate([(0.1, True), (0.2, True)], 0.05).certifiable)

# A run of ties must be accepted or rejected whole. Five tied wrong items at the
# lowest score cannot be split, so no threshold satisfies alpha.
tied = [(0.1, False)] * 5 + [(0.2, True)] * 15
ok &= check("tied run is not split", calibrate(tied, 0.05).threshold is None)

# Monotone transformation invariance: the guarantee depends on order alone.
base = [(0.1, True), (0.4, False), (0.6, True), (0.9, False)] * 6
squared = [(s ** 2, c) for s, c in base]
a, b = calibrate(base, 0.2), calibrate(squared, 0.2)
ok &= check("invariant under monotone transform", a.acceptance_rate == b.acceptance_rate and a.false_acceptance_rate == b.false_acceptance_rate)

ok &= check("leakage undecidable with one outcome", label_leakage([(0.1, False), (0.2, False)])["decidable"] is False)
ok &= check("leakage flagged when perfectly separated", label_leakage([(0.1, True), (0.9, False)])["suspected"] is True)
ok &= check("leakage clear when outcomes overlap", label_leakage([(0.1, True), (0.2, False), (0.3, True)])["suspected"] is False)

r = realized_risk([(0.1, True), (0.2, False), (0.9, True)], 0.5)
ok &= check("realized risk measured on held-out data", r["accepted"] == 2 and r["false_acceptance_rate"] == round(1/3, 6))
ok &= check("no threshold accepts nothing", realized_risk([(0.1, True)], None)["accepted"] == 0)

print("\nALL PASS" if ok else "\nFAILURES")
raise SystemExit(0 if ok else 1)
