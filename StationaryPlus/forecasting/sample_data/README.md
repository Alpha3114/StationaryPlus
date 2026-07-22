# Forecast demo datasets

Six ready-to-load SQL files that each simulate a specific trend/confidence
combo, so you can click **Run Forecast** on the admin Reports page and see
each behavior for yourself. All rows use an easy-to-spot `DEMO-` ID prefix.

| File | What you'll see |
|---|---|
| `demo_1_upward.sql`   | 8 months, steadily rising revenue → **upward** trend, **High** confidence |
| `demo_2_downward.sql` | 8 months, steadily falling revenue → **downward** trend, **High** confidence |
| `demo_3_moderate_confidence.sql` | 12 months, upward but noisy → **upward** trend, **Moderate** confidence |
| `demo_4_low_confidence.sql`      | 12 months, upward but very noisy → **upward** trend, **Low** confidence |
| `demo_5_flat.sql`     | 12 months, identical revenue every month → **flat** trend, **Low** confidence (a perfect flat forecast still shows Low — R² is undefined/0 when there's zero variance to explain, that's expected, not a bug) |
| `demo_6_seasonal.sql` | 26 months with a clear yearly wave + mild growth → the **Seasonal** model wins instead of Linear/Polynomial, **High** confidence |

## How to use

Your `payments`/`orders` tables already have real data, and the forecast
looks at **all** `VALID` payments from the last 36 months — so loading a
demo file adds to your real numbers rather than replacing them. To see a
demo trend in isolation:

**1. Hide your real data (reversible):**
```sql
CREATE TABLE IF NOT EXISTS _demo_valid_payment_backup AS
SELECT payment_id FROM payments WHERE verification_status = 'VALID';

UPDATE payments SET verification_status = 'PENDING'
WHERE payment_id IN (SELECT payment_id FROM _demo_valid_payment_backup);
```
(also in `hide_real_data.sql` in this folder)

**2. Load ONE demo file** (phpMyAdmin's Import tab, or):
```
mysql -u root stationaryplus < demo_1_upward.sql
```

**3. Go to the admin Reports page and click "Run Forecast".**

**4. When you're done looking, clean up and restore your real data:**
```
mysql -u root stationaryplus < demo_cleanup.sql
```
```sql
UPDATE payments SET verification_status = 'VALID'
WHERE payment_id IN (SELECT payment_id FROM _demo_valid_payment_backup);
DROP TABLE IF EXISTS _demo_valid_payment_backup;
```
(also in `restore_real_data.sql`)

Only ever load **one** demo file at a time — they're all dated to end in
the same month, so loading two at once mixes their trends together.
`demo_cleanup.sql` removes every `DEMO-` row regardless of which file(s)
you loaded, so it's always safe to run before switching demos or when
you're finished.
