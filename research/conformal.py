"""Group-conditional split-conformal risk control over an ordering.

Mirrors the Laravel ConformalCalibrator so results can be cross-checked between
the two implementations. Any divergence is a bug in one of them, and having two
independent implementations is the cheapest way to notice.

What is guaranteed: P(item auto-accepted AND wrong) <= alpha, for an item drawn
exchangeably from the same group as its calibration set. That is an
unconditional joint probability, deliberately not P(wrong | accepted), which is
a ratio of two random quantities and is not what this construction bounds.
"""

from __future__ import annotations

import math
from dataclasses import dataclass


@dataclass(frozen=True)
class GroupCalibration:
    group: str
    alpha: float
    n: int
    minimum_n: int
    threshold: float | None
    acceptance_rate: float
    false_acceptance_rate: float

    @property
    def certifiable(self) -> bool:
        return self.threshold is not None


def minimum_calibration_size(alpha: float) -> int:
    """Smallest calibration set that can satisfy the finite-sample bound.

    The correction contributes 1/(n+1) to the risk, so a group with fewer than
    1/alpha - 1 items cannot satisfy it at any threshold. This constrains
    low-resource groups first, which is why it is reported rather than hidden.
    """
    if not 0.0 < alpha < 1.0:
        raise ValueError("alpha must be in (0, 1)")
    return max(1, math.ceil(1.0 / alpha) - 1)


def calibrate(rows: list[tuple[float, bool]], alpha: float, group: str = "") -> GroupCalibration:
    """rows: (score, is_correct). Lower score = more conforming.

    Accept when score <= threshold, so risk is non-decreasing in the threshold.
    Scan upward and keep the largest threshold whose corrected risk still
    satisfies alpha, which maximises coverage subject to the bound. A run of
    tied scores is evaluated only at its end: accepting part of a tie is not
    realizable, and pretending otherwise is how a calibration silently
    overstates its coverage.
    """
    n = len(rows)
    minimum = minimum_calibration_size(alpha)
    if n == 0:
        return GroupCalibration(group, alpha, 0, minimum, None, 0.0, 0.0)

    ordered = sorted(rows, key=lambda r: r[0])
    threshold: float | None = None
    errors_at = accepted_at = 0
    errors = 0

    for i, (score, correct) in enumerate(ordered):
        if not correct:
            errors += 1
        if i + 1 < n and ordered[i + 1][0] == score:
            continue
        if (errors + 1) / (n + 1) <= alpha:
            threshold, errors_at, accepted_at = score, errors, i + 1

    return GroupCalibration(
        group, alpha, n, minimum, threshold,
        round(accepted_at / n, 6), round(errors_at / n, 6),
    )


def realized_risk(rows: list[tuple[float, bool]], threshold: float | None) -> dict[str, float | int | None]:
    """Measure a fitted threshold on held-out data."""
    if threshold is None or not rows:
        return {"n": len(rows), "accepted": 0, "false_acceptance_rate": 0.0, "error_among_accepted": None}
    accepted = [r for r in rows if r[0] <= threshold]
    wrong = sum(1 for _, correct in accepted if not correct)
    return {
        "n": len(rows),
        "accepted": len(accepted),
        "false_acceptance_rate": round(wrong / len(rows), 6),
        "error_among_accepted": round(wrong / len(accepted), 6) if accepted else None,
    }


def label_leakage(rows: list[tuple[float, bool]]) -> dict[str, object]:
    """Flag a score that already knows the answer.

    If no incorrect item scores below the worst correct one, the score separates
    outcomes perfectly, which in practice means it was derived from the label.
    Undecidable when only one outcome is present: answering "clear" there is the
    false confidence this check exists to prevent.
    """
    correct = [s for s, c in rows if c]
    incorrect = [s for s, c in rows if not c]
    if not correct or not incorrect:
        return {"decidable": False, "suspected": None,
                "reason": "Only one outcome present; separation cannot be shown or ruled out."}
    below = sum(1 for s in incorrect if s < max(correct))
    return {"decidable": True, "suspected": below == 0, "incorrect_below_worst_correct": below,
            "reason": "Score separates outcomes perfectly; likely derived from the label." if below == 0 else None}
