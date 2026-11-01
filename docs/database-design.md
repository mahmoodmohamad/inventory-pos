# تعديلات docs/database-design.md

## القسم 2 (جدول Current Implementation Status): أضف صف
| `vehicle_models` | Implemented | Shared catalog، FK على `vehicle_makes`، unique على `(vehicle_make_id, slug)`، فيه factory وseeder وtests |

وغيّر صف `vehicle_makes` إلى: Implemented (migration + model + factory + seeder + tests)
وحدّث: Current application models: `Shop`, `User`, `Category`, `VehicleMake`, `VehicleModel`

## القسم 3 (ERD): أضف
    VEHICLE_MAKES ||--o{ VEHICLE_MODELS : has

    VEHICLE_MODELS {
        bigint id PK
        bigint vehicle_make_id FK
        string name
        string name_ar "nullable"
        string slug
        boolean is_active
        timestamps timestamps
    }

وفي Notes: `vehicle_models.slug` unique داخل كل make فقط (`unique(vehicle_make_id, slug)`)، والـ FK بـ RESTRICT، ومفيش `shop_id`.

## القسم 4: أضف 4.5 Vehicle models
الجدول يحتوي `id`, `vehicle_make_id` (FK → `vehicle_makes.id`, RESTRICT), `name`, `name_ar` (nullable), `slug`, `is_active` (default true), timestamps، وunique على `(vehicle_make_id, slug)`. جدول مشترك.

## القسم 5 / Layer 5: أضف
- الـ seeder `VehicleCatalogSeeder` للكتالوج المشترك idempotent (`updateOrCreate`)، ومش محتاج tenant context (`VehicleCatalogTest`).
- Arch allowlist بقت: `Shop`, `User`, `VehicleMake`, `VehicleModel`.

## القسم 6: غيّر `vehicle_models (planned)` إلى `vehicle_models (implemented)`
## القسم 8: الخطوة 2: makes (done), models (done), variants.