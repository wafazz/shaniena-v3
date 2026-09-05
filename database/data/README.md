# Reference data

Static lookup data seeded by `database/seeders/ReferenceDataSeeder.php`.
These files are the seed source so a fresh clone can populate the lookups
without access to the source database.

| File | Rows | Origin |
|---|---|---|
| `list_country.json` | 5 | `shaniena_src.list_country` (from `base_ecom.sql`) |
| `all_country.json` | 352 | `shaniena_src.all_country` (from `base_ecom.sql`) |
| `postcode_my.csv.gz` | 56,234 | `shaniena_src.postcode_my` (from `base_ecom.sql`) |
| `state_my.json` | 16 | **Derived** — see below |

`state_my` was empty in the dump. The 16 codes are the distinct `state_code`
values present in `postcode_my` (13 states + 3 federal territories, i.e. the
complete set); the names are the standard Pos Malaysia readings of those codes.
Nothing here affects pricing.

## Not seeded: the `state` table

`state` carries `shipping_zone` (1 = Peninsular, 2 = Sabah/Sarawak/Labuan),
which drives both postage (`postage_cost`) and the COD benchmark fee
(`cod_charges`). It is money-affecting business configuration, it was empty in
the dump, and the source has no hardcoded zone mapping to port. It must come
from the live database or be confirmed by the merchant — see plan item 2.15.
