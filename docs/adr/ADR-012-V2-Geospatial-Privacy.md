# ADR-012: District Preferences Without Location Retention

## Status

Accepted

## Context

The public experience should open around relevant geography, but precise browser coordinates are sensitive and unnecessary for ongoing personalization.

## Decision

CivicLens may request browser geolocation only after a clear visitor action. The browser uses the coordinate ephemerally to suggest a district, then discards it. Anonymous users default to Dhaka. Authenticated users may save selected district IDs in account preferences; CivicLens does not store the raw coordinate, accuracy radius, or location trail.

Map-derived records preserve the precision and derivation of the public source itself. Ambiguous location matches remain unresolved until human review.

## Consequences

Users may need to confirm or correct a suggested district, but the system avoids retaining unnecessary precise location data and keeps preferences compatible with the normalized geography hierarchy.

## V2 Impact

The first public call to action is **Explore my district**. Notifications default to approved major changes and may be customized by category for saved districts.
