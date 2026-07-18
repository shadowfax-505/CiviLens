CREATE TABLE IF NOT EXISTS "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE IF NOT EXISTS "users"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "email" varchar not null,
  "email_verified_at" datetime,
  "password" varchar not null,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "avatar_path" varchar,
  "is_active" tinyint(1) not null default '1',
  "locked_at" datetime,
  "notification_preferences" text,
  "last_login_at" datetime,
  "password_changed_at" datetime
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE TABLE IF NOT EXISTS "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);
CREATE TABLE IF NOT EXISTS "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE IF NOT EXISTS "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE INDEX "cache_expiration_index" on "cache"("expiration");
CREATE TABLE IF NOT EXISTS "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE INDEX "cache_locks_expiration_index" on "cache_locks"("expiration");
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" varchar not null,
  "queue" varchar not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE INDEX "failed_jobs_connection_queue_failed_at_index" on "failed_jobs"(
  "connection",
  "queue",
  "failed_at"
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE IF NOT EXISTS "roles"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" varchar,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "roles_slug_unique" on "roles"("slug");
CREATE TABLE IF NOT EXISTS "role_user"(
  "id" integer primary key autoincrement not null,
  "role_id" integer not null,
  "user_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("role_id") references "roles"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "role_user_role_id_user_id_unique" on "role_user"(
  "role_id",
  "user_id"
);
CREATE INDEX "role_user_user_id_role_id_index" on "role_user"(
  "user_id",
  "role_id"
);
CREATE TABLE IF NOT EXISTS "role_permission"(
  "id" integer primary key autoincrement not null,
  "role_id" integer not null,
  "permission_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("role_id") references "roles"("id") on delete cascade,
  foreign key("permission_id") references "permissions"("id") on delete cascade
);
CREATE UNIQUE INDEX "role_permission_role_id_permission_id_unique" on "role_permission"(
  "role_id",
  "permission_id"
);
CREATE INDEX "role_permission_permission_id_role_id_index" on "role_permission"(
  "permission_id",
  "role_id"
);
CREATE INDEX "users_is_active_index" on "users"("is_active");
CREATE INDEX "users_locked_at_index" on "users"("locked_at");
CREATE INDEX "users_last_login_at_index" on "users"("last_login_at");
CREATE TABLE IF NOT EXISTS "permission_groups"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" varchar,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "permission_groups_slug_unique" on "permission_groups"(
  "slug"
);
CREATE TABLE IF NOT EXISTS "permissions"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "permission_group_id" integer,
  foreign key("permission_group_id") references "permission_groups"("id") on delete set null
);
CREATE UNIQUE INDEX "permissions_slug_unique" on "permissions"("slug");
CREATE INDEX "permissions_permission_group_id_slug_index" on "permissions"(
  "permission_group_id",
  "slug"
);
CREATE TABLE IF NOT EXISTS "account_activities"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "actor_id" integer,
  "event" varchar not null,
  "ip_address" varchar,
  "user_agent" text,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("actor_id") references "users"("id") on delete set null
);
CREATE INDEX "account_activities_user_id_event_index" on "account_activities"(
  "user_id",
  "event"
);
CREATE INDEX "account_activities_actor_id_event_index" on "account_activities"(
  "actor_id",
  "event"
);
CREATE INDEX "account_activities_event_index" on "account_activities"("event");
CREATE TABLE IF NOT EXISTS "countries"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "iso2" varchar not null,
  "iso3" varchar not null,
  "phone_code" varchar,
  "latitude" numeric,
  "longitude" numeric,
  "geojson" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime
);
CREATE UNIQUE INDEX "countries_name_unique" on "countries"("name");
CREATE INDEX "countries_name_iso2_index" on "countries"("name", "iso2");
CREATE UNIQUE INDEX "countries_iso2_unique" on "countries"("iso2");
CREATE UNIQUE INDEX "countries_iso3_unique" on "countries"("iso3");
CREATE TABLE IF NOT EXISTS "divisions"(
  "id" integer primary key autoincrement not null,
  "country_id" integer not null,
  "name" varchar not null,
  "code" varchar,
  "latitude" numeric,
  "longitude" numeric,
  "geojson" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("country_id") references "countries"("id") on delete restrict on update cascade
);
CREATE UNIQUE INDEX "divisions_country_id_name_unique" on "divisions"(
  "country_id",
  "name"
);
CREATE INDEX "divisions_country_id_code_index" on "divisions"(
  "country_id",
  "code"
);
CREATE TABLE IF NOT EXISTS "districts"(
  "id" integer primary key autoincrement not null,
  "division_id" integer not null,
  "name" varchar not null,
  "code" varchar,
  "latitude" numeric,
  "longitude" numeric,
  "geojson" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("division_id") references "divisions"("id") on delete restrict on update cascade
);
CREATE UNIQUE INDEX "districts_division_id_name_unique" on "districts"(
  "division_id",
  "name"
);
CREATE INDEX "districts_division_id_code_index" on "districts"(
  "division_id",
  "code"
);
CREATE TABLE IF NOT EXISTS "upazilas"(
  "id" integer primary key autoincrement not null,
  "district_id" integer not null,
  "name" varchar not null,
  "code" varchar,
  "latitude" numeric,
  "longitude" numeric,
  "geojson" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("district_id") references "districts"("id") on delete restrict on update cascade
);
CREATE UNIQUE INDEX "upazilas_district_id_name_unique" on "upazilas"(
  "district_id",
  "name"
);
CREATE INDEX "upazilas_district_id_code_index" on "upazilas"(
  "district_id",
  "code"
);
CREATE TABLE IF NOT EXISTS "unions"(
  "id" integer primary key autoincrement not null,
  "upazila_id" integer not null,
  "name" varchar not null,
  "type" varchar not null default 'union',
  "code" varchar,
  "latitude" numeric,
  "longitude" numeric,
  "geojson" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("upazila_id") references "upazilas"("id") on delete restrict on update cascade
);
CREATE UNIQUE INDEX "unions_upazila_id_name_unique" on "unions"(
  "upazila_id",
  "name"
);
CREATE INDEX "unions_upazila_id_type_index" on "unions"("upazila_id", "type");
CREATE INDEX "unions_upazila_id_code_index" on "unions"("upazila_id", "code");
CREATE TABLE IF NOT EXISTS "wards"(
  "id" integer primary key autoincrement not null,
  "union_id" integer not null,
  "name" varchar not null,
  "code" varchar,
  "latitude" numeric,
  "longitude" numeric,
  "geojson" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("union_id") references "unions"("id") on delete restrict on update cascade
);
CREATE UNIQUE INDEX "wards_union_id_name_unique" on "wards"(
  "union_id",
  "name"
);
CREATE INDEX "wards_union_id_code_index" on "wards"("union_id", "code");
CREATE TABLE IF NOT EXISTS "agency_types"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime
);
CREATE INDEX "agency_types_name_index" on "agency_types"("name");
CREATE UNIQUE INDEX "agency_types_slug_unique" on "agency_types"("slug");
CREATE TABLE IF NOT EXISTS "agencies"(
  "id" integer primary key autoincrement not null,
  "parent_id" integer,
  "agency_type_id" integer not null,
  "country_id" integer,
  "division_id" integer,
  "district_id" integer,
  "upazila_id" integer,
  "union_id" integer,
  "ward_id" integer,
  "name" varchar not null,
  "short_name" varchar,
  "slug" varchar not null,
  "description" text,
  "contact_person" varchar,
  "website" varchar,
  "email" varchar,
  "phone" varchar,
  "address" text,
  "status" varchar not null default 'active',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "latitude" numeric,
  "longitude" numeric,
  "geojson" text,
  foreign key("parent_id") references "agencies"("id") on delete set null on update cascade,
  foreign key("agency_type_id") references "agency_types"("id") on delete restrict on update cascade,
  foreign key("country_id") references "countries"("id") on delete set null on update cascade,
  foreign key("division_id") references "divisions"("id") on delete set null on update cascade,
  foreign key("district_id") references "districts"("id") on delete set null on update cascade,
  foreign key("upazila_id") references "upazilas"("id") on delete set null on update cascade,
  foreign key("union_id") references "unions"("id") on delete set null on update cascade,
  foreign key("ward_id") references "wards"("id") on delete set null on update cascade
);
CREATE INDEX "agencies_agency_type_id_status_index" on "agencies"(
  "agency_type_id",
  "status"
);
CREATE INDEX "agencies_parent_id_status_index" on "agencies"(
  "parent_id",
  "status"
);
CREATE INDEX "agencies_country_id_division_id_district_id_index" on "agencies"(
  "country_id",
  "division_id",
  "district_id"
);
CREATE INDEX "agencies_upazila_id_union_id_ward_id_index" on "agencies"(
  "upazila_id",
  "union_id",
  "ward_id"
);
CREATE INDEX "agencies_name_index" on "agencies"("name");
CREATE UNIQUE INDEX "agencies_slug_unique" on "agencies"("slug");
CREATE TABLE IF NOT EXISTS "agency_user"(
  "id" integer primary key autoincrement not null,
  "agency_id" integer not null,
  "user_id" integer not null,
  "relationship" varchar not null default 'member',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("agency_id") references "agencies"("id") on delete cascade on update cascade,
  foreign key("user_id") references "users"("id") on delete cascade on update cascade
);
CREATE UNIQUE INDEX "agency_user_agency_id_user_id_unique" on "agency_user"(
  "agency_id",
  "user_id"
);
CREATE INDEX "agency_user_user_id_relationship_index" on "agency_user"(
  "user_id",
  "relationship"
);
CREATE TABLE IF NOT EXISTS "project_categories"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime
);
CREATE INDEX "project_categories_name_index" on "project_categories"("name");
CREATE UNIQUE INDEX "project_categories_slug_unique" on "project_categories"(
  "slug"
);
CREATE TABLE IF NOT EXISTS "project_statuses"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "is_terminal" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime
);
CREATE INDEX "project_statuses_sort_order_name_index" on "project_statuses"(
  "sort_order",
  "name"
);
CREATE UNIQUE INDEX "project_statuses_slug_unique" on "project_statuses"(
  "slug"
);
CREATE TABLE IF NOT EXISTS "project_priorities"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime
);
CREATE INDEX "project_priorities_sort_order_name_index" on "project_priorities"(
  "sort_order",
  "name"
);
CREATE UNIQUE INDEX "project_priorities_slug_unique" on "project_priorities"(
  "slug"
);
CREATE TABLE IF NOT EXISTS "funding_sources"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime
);
CREATE INDEX "funding_sources_name_index" on "funding_sources"("name");
CREATE UNIQUE INDEX "funding_sources_slug_unique" on "funding_sources"("slug");
CREATE TABLE IF NOT EXISTS "fiscal_years"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "starts_on" date not null,
  "ends_on" date not null,
  "is_active" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime
);
CREATE INDEX "fiscal_years_is_active_starts_on_index" on "fiscal_years"(
  "is_active",
  "starts_on"
);
CREATE UNIQUE INDEX "fiscal_years_name_unique" on "fiscal_years"("name");
CREATE TABLE IF NOT EXISTS "projects"(
  "id" integer primary key autoincrement not null,
  "project_code" varchar not null,
  "name" varchar not null,
  "short_name" varchar,
  "slug" varchar not null,
  "description" text,
  "agency_id" integer not null,
  "parent_id" integer,
  "project_category_id" integer not null,
  "project_status_id" integer not null,
  "project_priority_id" integer not null,
  "funding_source_id" integer not null,
  "fiscal_year_id" integer not null,
  "country_id" integer,
  "division_id" integer,
  "district_id" integer,
  "upazila_id" integer,
  "union_id" integer,
  "ward_id" integer,
  "progress_percentage" integer not null default '0',
  "planned_start_date" date,
  "actual_start_date" date,
  "planned_end_date" date,
  "actual_end_date" date,
  "latitude" numeric,
  "longitude" numeric,
  "geojson" text,
  "featured_image_path" varchar,
  "is_public" tinyint(1) not null default '0',
  "is_active" tinyint(1) not null default '1',
  "archived_at" datetime,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("agency_id") references "agencies"("id") on delete restrict on update cascade,
  foreign key("parent_id") references "projects"("id") on delete set null on update cascade,
  foreign key("project_category_id") references "project_categories"("id") on delete restrict on update cascade,
  foreign key("project_status_id") references "project_statuses"("id") on delete restrict on update cascade,
  foreign key("project_priority_id") references "project_priorities"("id") on delete restrict on update cascade,
  foreign key("funding_source_id") references "funding_sources"("id") on delete restrict on update cascade,
  foreign key("fiscal_year_id") references "fiscal_years"("id") on delete restrict on update cascade,
  foreign key("country_id") references "countries"("id") on delete set null on update cascade,
  foreign key("division_id") references "divisions"("id") on delete set null on update cascade,
  foreign key("district_id") references "districts"("id") on delete set null on update cascade,
  foreign key("upazila_id") references "upazilas"("id") on delete set null on update cascade,
  foreign key("union_id") references "unions"("id") on delete set null on update cascade,
  foreign key("ward_id") references "wards"("id") on delete set null on update cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "projects_agency_id_project_status_id_index" on "projects"(
  "agency_id",
  "project_status_id"
);
CREATE INDEX "projects_project_category_id_project_priority_id_index" on "projects"(
  "project_category_id",
  "project_priority_id"
);
CREATE INDEX "projects_funding_source_id_fiscal_year_id_index" on "projects"(
  "funding_source_id",
  "fiscal_year_id"
);
CREATE INDEX "projects_country_id_division_id_district_id_index" on "projects"(
  "country_id",
  "division_id",
  "district_id"
);
CREATE INDEX "projects_upazila_id_union_id_ward_id_index" on "projects"(
  "upazila_id",
  "union_id",
  "ward_id"
);
CREATE INDEX "projects_is_public_is_active_archived_at_index" on "projects"(
  "is_public",
  "is_active",
  "archived_at"
);
CREATE INDEX "projects_planned_start_date_planned_end_date_index" on "projects"(
  "planned_start_date",
  "planned_end_date"
);
CREATE UNIQUE INDEX "projects_project_code_unique" on "projects"(
  "project_code"
);
CREATE UNIQUE INDEX "projects_slug_unique" on "projects"("slug");
CREATE TABLE IF NOT EXISTS "project_activities"(
  "id" integer primary key autoincrement not null,
  "project_id" integer not null,
  "actor_id" integer,
  "event" varchar not null,
  "old_values" text,
  "new_values" text,
  "ip_address" varchar,
  "user_agent" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("project_id") references "projects"("id") on delete cascade on update cascade,
  foreign key("actor_id") references "users"("id") on delete set null
);
CREATE INDEX "project_activities_project_id_event_index" on "project_activities"(
  "project_id",
  "event"
);
CREATE INDEX "project_activities_actor_id_event_index" on "project_activities"(
  "actor_id",
  "event"
);
CREATE INDEX "project_activities_event_index" on "project_activities"("event");
CREATE TABLE IF NOT EXISTS "budget_categories"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime
);
CREATE INDEX "budget_categories_is_active_name_index" on "budget_categories"(
  "is_active",
  "name"
);
CREATE UNIQUE INDEX "budget_categories_slug_unique" on "budget_categories"(
  "slug"
);
CREATE TABLE IF NOT EXISTS "budget_types"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime
);
CREATE INDEX "budget_types_is_active_name_index" on "budget_types"(
  "is_active",
  "name"
);
CREATE UNIQUE INDEX "budget_types_slug_unique" on "budget_types"("slug");
CREATE TABLE IF NOT EXISTS "budget_statuses"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime
);
CREATE INDEX "budget_statuses_is_active_sort_order_index" on "budget_statuses"(
  "is_active",
  "sort_order"
);
CREATE UNIQUE INDEX "budget_statuses_slug_unique" on "budget_statuses"("slug");
CREATE TABLE IF NOT EXISTS "budget_transaction_types"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "direction" varchar not null default 'neutral',
  "description" text,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime
);
CREATE INDEX "budget_transaction_types_is_active_direction_index" on "budget_transaction_types"(
  "is_active",
  "direction"
);
CREATE UNIQUE INDEX "budget_transaction_types_slug_unique" on "budget_transaction_types"(
  "slug"
);
CREATE TABLE IF NOT EXISTS "budgets"(
  "id" integer primary key autoincrement not null,
  "project_id" integer not null,
  "fiscal_year_id" integer not null,
  "budget_type_id" integer not null,
  "funding_source_id" integer not null,
  "budget_category_id" integer not null,
  "budget_status_id" integer not null,
  "original_allocation" numeric not null default '0',
  "current_allocation" numeric not null default '0',
  "reserved_amount" numeric not null default '0',
  "committed_amount" numeric not null default '0',
  "actual_expenditure" numeric not null default '0',
  "currency" varchar not null default 'BDT',
  "notes" text,
  "is_active" tinyint(1) not null default '1',
  "archived_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("project_id") references "projects"("id") on delete restrict on update cascade,
  foreign key("fiscal_year_id") references "fiscal_years"("id") on delete restrict on update cascade,
  foreign key("budget_type_id") references "budget_types"("id") on delete restrict on update cascade,
  foreign key("funding_source_id") references "funding_sources"("id") on delete restrict on update cascade,
  foreign key("budget_category_id") references "budget_categories"("id") on delete restrict on update cascade,
  foreign key("budget_status_id") references "budget_statuses"("id") on delete restrict on update cascade
);
CREATE INDEX "budgets_project_id_fiscal_year_id_index" on "budgets"(
  "project_id",
  "fiscal_year_id"
);
CREATE INDEX "budgets_budget_type_id_budget_status_id_index" on "budgets"(
  "budget_type_id",
  "budget_status_id"
);
CREATE INDEX "budgets_funding_source_id_budget_category_id_index" on "budgets"(
  "funding_source_id",
  "budget_category_id"
);
CREATE INDEX "budgets_current_allocation_actual_expenditure_index" on "budgets"(
  "current_allocation",
  "actual_expenditure"
);
CREATE INDEX "budgets_is_active_archived_at_index" on "budgets"(
  "is_active",
  "archived_at"
);
CREATE TABLE IF NOT EXISTS "budget_revisions"(
  "id" integer primary key autoincrement not null,
  "budget_id" integer not null,
  "revision_number" integer not null,
  "previous_allocation" numeric not null,
  "new_allocation" numeric not null,
  "difference" numeric not null,
  "reason" text not null,
  "approval_date" date,
  "approved_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("budget_id") references "budgets"("id") on delete cascade on update cascade,
  foreign key("approved_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "budget_revisions_budget_id_revision_number_unique" on "budget_revisions"(
  "budget_id",
  "revision_number"
);
CREATE INDEX "budget_revisions_approved_by_approval_date_index" on "budget_revisions"(
  "approved_by",
  "approval_date"
);
CREATE TABLE IF NOT EXISTS "budget_transactions"(
  "id" integer primary key autoincrement not null,
  "budget_id" integer not null,
  "budget_transaction_type_id" integer not null,
  "user_id" integer,
  "amount" numeric not null,
  "transaction_date" date not null,
  "description" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("budget_id") references "budgets"("id") on delete restrict on update cascade,
  foreign key("budget_transaction_type_id") references "budget_transaction_types"("id") on delete restrict on update cascade,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE INDEX "budget_transactions_budget_id_transaction_date_index" on "budget_transactions"(
  "budget_id",
  "transaction_date"
);
CREATE INDEX "budget_tx_type_date_idx" on "budget_transactions"(
  "budget_transaction_type_id",
  "transaction_date"
);
CREATE INDEX "budget_transactions_user_id_transaction_date_index" on "budget_transactions"(
  "user_id",
  "transaction_date"
);
CREATE TABLE IF NOT EXISTS "procurement_methods"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime
);
CREATE INDEX "procurement_methods_is_active_name_index" on "procurement_methods"(
  "is_active",
  "name"
);
CREATE UNIQUE INDEX "procurement_methods_slug_unique" on "procurement_methods"(
  "slug"
);
CREATE TABLE IF NOT EXISTS "tender_categories"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime
);
CREATE INDEX "tender_categories_is_active_name_index" on "tender_categories"(
  "is_active",
  "name"
);
CREATE UNIQUE INDEX "tender_categories_slug_unique" on "tender_categories"(
  "slug"
);
CREATE TABLE IF NOT EXISTS "tender_statuses"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime
);
CREATE INDEX "tender_statuses_is_active_sort_order_index" on "tender_statuses"(
  "is_active",
  "sort_order"
);
CREATE UNIQUE INDEX "tender_statuses_slug_unique" on "tender_statuses"("slug");
CREATE TABLE IF NOT EXISTS "tenders"(
  "id" integer primary key autoincrement not null,
  "project_id" integer not null,
  "budget_id" integer not null,
  "agency_id" integer not null,
  "procurement_method_id" integer not null,
  "tender_category_id" integer not null,
  "tender_status_id" integer not null,
  "tender_number" varchar not null,
  "title" varchar not null,
  "slug" varchar not null,
  "description" text,
  "published_at" datetime,
  "closing_at" datetime,
  "closed_at" datetime,
  "is_public" tinyint(1) not null default '0',
  "is_active" tinyint(1) not null default '1',
  "archived_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("project_id") references "projects"("id") on delete restrict on update cascade,
  foreign key("budget_id") references "budgets"("id") on delete restrict on update cascade,
  foreign key("agency_id") references "agencies"("id") on delete restrict on update cascade,
  foreign key("procurement_method_id") references "procurement_methods"("id") on delete restrict on update cascade,
  foreign key("tender_category_id") references "tender_categories"("id") on delete restrict on update cascade,
  foreign key("tender_status_id") references "tender_statuses"("id") on delete restrict on update cascade
);
CREATE INDEX "tenders_project_id_budget_id_index" on "tenders"(
  "project_id",
  "budget_id"
);
CREATE INDEX "tenders_agency_id_tender_status_id_index" on "tenders"(
  "agency_id",
  "tender_status_id"
);
CREATE INDEX "tenders_procurement_method_id_tender_category_id_index" on "tenders"(
  "procurement_method_id",
  "tender_category_id"
);
CREATE INDEX "tenders_published_at_closing_at_index" on "tenders"(
  "published_at",
  "closing_at"
);
CREATE INDEX "tenders_is_active_archived_at_index" on "tenders"(
  "is_active",
  "archived_at"
);
CREATE UNIQUE INDEX "tenders_tender_number_unique" on "tenders"(
  "tender_number"
);
CREATE UNIQUE INDEX "tenders_slug_unique" on "tenders"("slug");
CREATE TABLE IF NOT EXISTS "tender_lots"(
  "id" integer primary key autoincrement not null,
  "tender_id" integer not null,
  "lot_number" varchar not null,
  "title" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("tender_id") references "tenders"("id") on delete cascade on update cascade
);
CREATE UNIQUE INDEX "tender_lots_tender_id_lot_number_unique" on "tender_lots"(
  "tender_id",
  "lot_number"
);
CREATE TABLE IF NOT EXISTS "bid_submissions"(
  "id" integer primary key autoincrement not null,
  "tender_id" integer not null,
  "bidder_organization_id" integer not null,
  "reference_number" varchar not null,
  "submitted_at" datetime,
  "technical_score" numeric,
  "financial_score" numeric,
  "status" varchar not null default 'submitted',
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "bid_amount" numeric,
  "bid_valid_until" date,
  "bid_security_amount" numeric,
  "technical_proposal_summary" text,
  "financial_proposal_summary" text,
  "opened_at" datetime,
  "withdrawn_at" datetime,
  foreign key("tender_id") references "tenders"("id") on delete restrict on update cascade,
  foreign key("bidder_organization_id") references "bidder_organizations"("id") on delete restrict on update cascade
);
CREATE INDEX "bid_submissions_tender_id_status_index" on "bid_submissions"(
  "tender_id",
  "status"
);
CREATE INDEX "bid_submissions_bidder_organization_id_submitted_at_index" on "bid_submissions"(
  "bidder_organization_id",
  "submitted_at"
);
CREATE UNIQUE INDEX "bid_submissions_reference_number_unique" on "bid_submissions"(
  "reference_number"
);
CREATE TABLE IF NOT EXISTS "bid_documents"(
  "id" integer primary key autoincrement not null,
  "bid_submission_id" integer not null,
  "uploaded_by" integer,
  "title" varchar not null,
  "document_type" varchar,
  "file_path" varchar not null,
  "uploaded_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("bid_submission_id") references "bid_submissions"("id") on delete cascade on update cascade,
  foreign key("uploaded_by") references "users"("id") on delete set null
);
CREATE INDEX "bid_documents_bid_submission_id_document_type_index" on "bid_documents"(
  "bid_submission_id",
  "document_type"
);
CREATE TABLE IF NOT EXISTS "evaluation_committees"(
  "id" integer primary key autoincrement not null,
  "tender_id" integer not null,
  "name" varchar not null,
  "formed_at" date,
  "status" varchar not null default 'active',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("tender_id") references "tenders"("id") on delete cascade on update cascade
);
CREATE INDEX "evaluation_committees_tender_id_status_index" on "evaluation_committees"(
  "tender_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "committee_members"(
  "id" integer primary key autoincrement not null,
  "evaluation_committee_id" integer not null,
  "user_id" integer,
  "name" varchar not null,
  "role" varchar,
  "email" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("evaluation_committee_id") references "evaluation_committees"("id") on delete cascade on update cascade,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE INDEX "committee_members_evaluation_committee_id_role_index" on "committee_members"(
  "evaluation_committee_id",
  "role"
);
CREATE TABLE IF NOT EXISTS "evaluation_criteria"(
  "id" integer primary key autoincrement not null,
  "tender_id" integer not null,
  "name" varchar not null,
  "description" text,
  "max_score" numeric not null default '100',
  "weight" numeric not null default '0',
  "sort_order" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("tender_id") references "tenders"("id") on delete cascade on update cascade
);
CREATE INDEX "evaluation_criteria_tender_id_sort_order_index" on "evaluation_criteria"(
  "tender_id",
  "sort_order"
);
CREATE TABLE IF NOT EXISTS "evaluation_scores"(
  "id" integer primary key autoincrement not null,
  "bid_submission_id" integer not null,
  "evaluation_criterion_id" integer not null,
  "committee_member_id" integer,
  "score" numeric not null,
  "comments" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("bid_submission_id") references "bid_submissions"("id") on delete cascade on update cascade,
  foreign key("evaluation_criterion_id") references "evaluation_criteria"("id") on delete cascade on update cascade,
  foreign key("committee_member_id") references "committee_members"("id") on delete set null
);
CREATE UNIQUE INDEX "evaluation_scores_unique_reviewer_score" on "evaluation_scores"(
  "bid_submission_id",
  "evaluation_criterion_id",
  "committee_member_id"
);
CREATE TABLE IF NOT EXISTS "awards"(
  "id" integer primary key autoincrement not null,
  "tender_id" integer not null,
  "bid_submission_id" integer not null,
  "awarded_at" date,
  "status" varchar not null default 'pending',
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "public_disclosure_status" varchar not null default 'internal',
  foreign key("tender_id") references "tenders"("id") on delete restrict on update cascade,
  foreign key("bid_submission_id") references "bid_submissions"("id") on delete restrict on update cascade
);
CREATE INDEX "awards_tender_id_status_index" on "awards"(
  "tender_id",
  "status"
);
CREATE INDEX "awards_bid_submission_id_awarded_at_index" on "awards"(
  "bid_submission_id",
  "awarded_at"
);
CREATE TABLE IF NOT EXISTS "contracts"(
  "id" integer primary key autoincrement not null,
  "award_id" integer not null,
  "bid_submission_id" integer not null,
  "project_id" integer not null,
  "budget_id" integer not null,
  "contract_number" varchar not null,
  "title" varchar not null,
  "status" varchar not null default 'draft',
  "signed_at" date,
  "start_date" date,
  "end_date" date,
  "notes" text,
  "archived_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("award_id") references "awards"("id") on delete restrict on update cascade,
  foreign key("bid_submission_id") references "bid_submissions"("id") on delete restrict on update cascade,
  foreign key("project_id") references "projects"("id") on delete restrict on update cascade,
  foreign key("budget_id") references "budgets"("id") on delete restrict on update cascade
);
CREATE INDEX "contracts_project_id_budget_id_index" on "contracts"(
  "project_id",
  "budget_id"
);
CREATE INDEX "contracts_award_id_status_index" on "contracts"(
  "award_id",
  "status"
);
CREATE INDEX "contracts_start_date_end_date_index" on "contracts"(
  "start_date",
  "end_date"
);
CREATE UNIQUE INDEX "contracts_contract_number_unique" on "contracts"(
  "contract_number"
);
CREATE TABLE IF NOT EXISTS "variation_orders"(
  "id" integer primary key autoincrement not null,
  "contract_id" integer not null,
  "title" varchar not null,
  "description" text,
  "approved_at" date,
  "status" varchar not null default 'pending',
  "created_at" datetime,
  "updated_at" datetime,
  "approved_amount" numeric,
  "schedule_extension_days" integer,
  "reason" text,
  foreign key("contract_id") references "contracts"("id") on delete restrict on update cascade
);
CREATE INDEX "variation_orders_contract_id_status_index" on "variation_orders"(
  "contract_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "contract_extensions"(
  "id" integer primary key autoincrement not null,
  "contract_id" integer not null,
  "previous_end_date" date not null,
  "new_end_date" date not null,
  "reason" text not null,
  "approved_at" date,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("contract_id") references "contracts"("id") on delete restrict on update cascade
);
CREATE INDEX "contract_extensions_contract_id_new_end_date_index" on "contract_extensions"(
  "contract_id",
  "new_end_date"
);
CREATE TABLE IF NOT EXISTS "liquidated_damages"(
  "id" integer primary key autoincrement not null,
  "contract_id" integer not null,
  "reason" text not null,
  "assessed_at" date,
  "days_delayed" integer,
  "assessed_amount" numeric,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("contract_id") references "contracts"("id") on delete restrict on update cascade
);
CREATE INDEX "liquidated_damages_contract_id_assessed_at_index" on "liquidated_damages"(
  "contract_id",
  "assessed_at"
);
CREATE TABLE IF NOT EXISTS "completion_certificates"(
  "id" integer primary key autoincrement not null,
  "contract_id" integer not null,
  "issued_by" integer,
  "certificate_number" varchar,
  "issued_at" date,
  "status" varchar not null default 'draft',
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("contract_id") references "contracts"("id") on delete restrict on update cascade,
  foreign key("issued_by") references "users"("id") on delete set null
);
CREATE INDEX "completion_certificates_contract_id_status_index" on "completion_certificates"(
  "contract_id",
  "status"
);
CREATE UNIQUE INDEX "completion_certificates_certificate_number_unique" on "completion_certificates"(
  "certificate_number"
);
CREATE TABLE IF NOT EXISTS "procurement_activities"(
  "id" integer primary key autoincrement not null,
  "tender_id" integer,
  "contract_id" integer,
  "actor_id" integer,
  "event" varchar not null,
  "description" text,
  "old_values" text,
  "new_values" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("tender_id") references "tenders"("id") on delete restrict on update cascade,
  foreign key("contract_id") references "contracts"("id") on delete restrict on update cascade,
  foreign key("actor_id") references "users"("id") on delete set null
);
CREATE INDEX "procurement_activities_tender_id_created_at_index" on "procurement_activities"(
  "tender_id",
  "created_at"
);
CREATE INDEX "procurement_activities_contract_id_created_at_index" on "procurement_activities"(
  "contract_id",
  "created_at"
);
CREATE INDEX "procurement_activities_actor_id_created_at_index" on "procurement_activities"(
  "actor_id",
  "created_at"
);
CREATE INDEX "procurement_activities_event_index" on "procurement_activities"(
  "event"
);
CREATE TABLE IF NOT EXISTS "organization_company_types"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "organization_company_types_slug_unique" on "organization_company_types"(
  "slug"
);
CREATE INDEX "organization_company_types_is_active_index" on "organization_company_types"(
  "is_active"
);
CREATE TABLE IF NOT EXISTS "organization_industries"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "organization_industries_slug_unique" on "organization_industries"(
  "slug"
);
CREATE INDEX "organization_industries_is_active_index" on "organization_industries"(
  "is_active"
);
CREATE TABLE IF NOT EXISTS "contractor_categories"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "contractor_categories_slug_unique" on "contractor_categories"(
  "slug"
);
CREATE INDEX "contractor_categories_is_active_index" on "contractor_categories"(
  "is_active"
);
CREATE TABLE IF NOT EXISTS "contractor_classifications"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "contractor_classifications_slug_unique" on "contractor_classifications"(
  "slug"
);
CREATE INDEX "contractor_classifications_is_active_index" on "contractor_classifications"(
  "is_active"
);
CREATE TABLE IF NOT EXISTS "contractor_registration_statuses"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "contractor_registration_statuses_slug_unique" on "contractor_registration_statuses"(
  "slug"
);
CREATE INDEX "contractor_registration_statuses_is_active_index" on "contractor_registration_statuses"(
  "is_active"
);
CREATE TABLE IF NOT EXISTS "contractor_risk_levels"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "contractor_risk_levels_slug_unique" on "contractor_risk_levels"(
  "slug"
);
CREATE INDEX "contractor_risk_levels_is_active_index" on "contractor_risk_levels"(
  "is_active"
);
CREATE TABLE IF NOT EXISTS "license_types"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "license_types_slug_unique" on "license_types"("slug");
CREATE INDEX "license_types_is_active_index" on "license_types"("is_active");
CREATE TABLE IF NOT EXISTS "certification_types"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "certification_types_slug_unique" on "certification_types"(
  "slug"
);
CREATE INDEX "certification_types_is_active_index" on "certification_types"(
  "is_active"
);
CREATE TABLE IF NOT EXISTS "compliance_types"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "compliance_types_slug_unique" on "compliance_types"(
  "slug"
);
CREATE INDEX "compliance_types_is_active_index" on "compliance_types"(
  "is_active"
);
CREATE TABLE IF NOT EXISTS "compliance_statuses"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "compliance_statuses_slug_unique" on "compliance_statuses"(
  "slug"
);
CREATE INDEX "compliance_statuses_is_active_index" on "compliance_statuses"(
  "is_active"
);
CREATE TABLE IF NOT EXISTS "contractor_activity_types"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "contractor_activity_types_slug_unique" on "contractor_activity_types"(
  "slug"
);
CREATE INDEX "contractor_activity_types_is_active_index" on "contractor_activity_types"(
  "is_active"
);
CREATE TABLE IF NOT EXISTS "organizations"(
  "id" integer primary key autoincrement not null,
  "organization_company_type_id" integer not null,
  "organization_industry_id" integer not null,
  "country_id" integer,
  "division_id" integer,
  "district_id" integer,
  "upazila_id" integer,
  "union_id" integer,
  "ward_id" integer,
  "legal_name" varchar not null,
  "trade_name" varchar,
  "registration_number" varchar not null,
  "tax_identification_number" varchar,
  "website" varchar,
  "email" varchar,
  "phone" varchar,
  "headquarters_address" text,
  "status" varchar not null default 'active',
  "established_date" date,
  "created_by" integer,
  "updated_by" integer,
  "archived_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "headquarters_latitude" numeric,
  "headquarters_longitude" numeric,
  "headquarters_geojson" text,
  foreign key("organization_company_type_id") references "organization_company_types"("id") on delete restrict on update cascade,
  foreign key("organization_industry_id") references "organization_industries"("id") on delete restrict on update cascade,
  foreign key("country_id") references "countries"("id") on delete set null,
  foreign key("division_id") references "divisions"("id") on delete set null,
  foreign key("district_id") references "districts"("id") on delete set null,
  foreign key("upazila_id") references "upazilas"("id") on delete set null,
  foreign key("union_id") references "unions"("id") on delete set null,
  foreign key("ward_id") references "wards"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "organizations_type_industry_idx" on "organizations"(
  "organization_company_type_id",
  "organization_industry_id"
);
CREATE INDEX "organizations_geography_idx" on "organizations"(
  "country_id",
  "district_id"
);
CREATE INDEX "organizations_search_idx" on "organizations"(
  "legal_name",
  "registration_number"
);
CREATE UNIQUE INDEX "organizations_registration_number_unique" on "organizations"(
  "registration_number"
);
CREATE UNIQUE INDEX "organizations_tax_identification_number_unique" on "organizations"(
  "tax_identification_number"
);
CREATE INDEX "organizations_status_index" on "organizations"("status");
CREATE INDEX "organizations_archived_at_index" on "organizations"(
  "archived_at"
);
CREATE TABLE IF NOT EXISTS "branch_offices"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "country_id" integer,
  "division_id" integer,
  "district_id" integer,
  "upazila_id" integer,
  "union_id" integer,
  "ward_id" integer,
  "address" text not null,
  "phone" varchar,
  "email" varchar,
  "latitude" numeric,
  "longitude" numeric,
  "status" varchar not null default 'active',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade on update cascade,
  foreign key("country_id") references "countries"("id") on delete set null,
  foreign key("division_id") references "divisions"("id") on delete set null,
  foreign key("district_id") references "districts"("id") on delete set null,
  foreign key("upazila_id") references "upazilas"("id") on delete set null,
  foreign key("union_id") references "unions"("id") on delete set null,
  foreign key("ward_id") references "wards"("id") on delete set null
);
CREATE INDEX "branch_offices_organization_id_status_index" on "branch_offices"(
  "organization_id",
  "status"
);
CREATE INDEX "branch_offices_geography_idx" on "branch_offices"(
  "country_id",
  "district_id"
);
CREATE INDEX "branch_offices_status_index" on "branch_offices"("status");
CREATE TABLE IF NOT EXISTS "contractor_profiles"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "contractor_category_id" integer not null,
  "contractor_classification_id" integer not null,
  "contractor_registration_status_id" integer not null,
  "contractor_risk_level_id" integer not null,
  "is_active" tinyint(1) not null default '1',
  "is_suspended" tinyint(1) not null default '0',
  "is_blacklisted" tinyint(1) not null default '0',
  "is_public" tinyint(1) not null default '1',
  "archived_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade on update cascade,
  foreign key("contractor_category_id") references "contractor_categories"("id") on delete restrict on update cascade,
  foreign key("contractor_classification_id") references "contractor_classifications"("id") on delete restrict on update cascade,
  foreign key("contractor_registration_status_id") references "contractor_registration_statuses"("id") on delete restrict on update cascade,
  foreign key("contractor_risk_level_id") references "contractor_risk_levels"("id") on delete restrict on update cascade
);
CREATE INDEX "contractor_profiles_category_risk_idx" on "contractor_profiles"(
  "contractor_category_id",
  "contractor_risk_level_id"
);
CREATE INDEX "contractor_profiles_status_active_idx" on "contractor_profiles"(
  "contractor_registration_status_id",
  "is_active"
);
CREATE UNIQUE INDEX "contractor_profiles_organization_id_unique" on "contractor_profiles"(
  "organization_id"
);
CREATE INDEX "contractor_profiles_is_active_index" on "contractor_profiles"(
  "is_active"
);
CREATE INDEX "contractor_profiles_is_suspended_index" on "contractor_profiles"(
  "is_suspended"
);
CREATE INDEX "contractor_profiles_is_blacklisted_index" on "contractor_profiles"(
  "is_blacklisted"
);
CREATE INDEX "contractor_profiles_is_public_index" on "contractor_profiles"(
  "is_public"
);
CREATE INDEX "contractor_profiles_archived_at_index" on "contractor_profiles"(
  "archived_at"
);
CREATE TABLE IF NOT EXISTS "directors"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "position" varchar not null,
  "appointment_date" date,
  "end_date" date,
  "email" varchar,
  "phone" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade on update cascade
);
CREATE INDEX "directors_organization_id_position_index" on "directors"(
  "organization_id",
  "position"
);
CREATE TABLE IF NOT EXISTS "contact_people"(
  "id" integer primary key autoincrement not null,
  "organization_id" integer not null,
  "name" varchar not null,
  "position" varchar,
  "email" varchar,
  "phone" varchar,
  "is_primary" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("organization_id") references "organizations"("id") on delete cascade on update cascade
);
CREATE INDEX "contact_people_organization_id_is_primary_index" on "contact_people"(
  "organization_id",
  "is_primary"
);
CREATE INDEX "contact_people_is_primary_index" on "contact_people"(
  "is_primary"
);
CREATE TABLE IF NOT EXISTS "contractor_licenses"(
  "id" integer primary key autoincrement not null,
  "contractor_profile_id" integer not null,
  "license_type_id" integer not null,
  "license_number" varchar not null,
  "issuing_authority" varchar not null,
  "issue_date" date,
  "expiry_date" date,
  "status" varchar not null default 'active',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("contractor_profile_id") references "contractor_profiles"("id") on delete cascade on update cascade,
  foreign key("license_type_id") references "license_types"("id") on delete restrict on update cascade
);
CREATE UNIQUE INDEX "contractor_licenses_license_type_id_license_number_unique" on "contractor_licenses"(
  "license_type_id",
  "license_number"
);
CREATE INDEX "contractor_licenses_contractor_profile_id_status_index" on "contractor_licenses"(
  "contractor_profile_id",
  "status"
);
CREATE INDEX "contractor_licenses_expiry_date_index" on "contractor_licenses"(
  "expiry_date"
);
CREATE INDEX "contractor_licenses_status_index" on "contractor_licenses"(
  "status"
);
CREATE TABLE IF NOT EXISTS "contractor_certifications"(
  "id" integer primary key autoincrement not null,
  "contractor_profile_id" integer not null,
  "certification_type_id" integer not null,
  "certificate_number" varchar,
  "issuing_authority" varchar,
  "issue_date" date,
  "expiry_date" date,
  "status" varchar not null default 'active',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("contractor_profile_id") references "contractor_profiles"("id") on delete cascade on update cascade,
  foreign key("certification_type_id") references "certification_types"("id") on delete restrict on update cascade
);
CREATE INDEX "contractor_certifications_profile_type_idx" on "contractor_certifications"(
  "contractor_profile_id",
  "certification_type_id"
);
CREATE INDEX "contractor_certifications_expiry_date_index" on "contractor_certifications"(
  "expiry_date"
);
CREATE INDEX "contractor_certifications_status_index" on "contractor_certifications"(
  "status"
);
CREATE TABLE IF NOT EXISTS "insurance_policies"(
  "id" integer primary key autoincrement not null,
  "contractor_profile_id" integer not null,
  "provider" varchar not null,
  "policy_number" varchar not null,
  "coverage_amount" numeric not null default '0',
  "currency" varchar not null default 'BDT',
  "expiry_date" date,
  "status" varchar not null default 'active',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("contractor_profile_id") references "contractor_profiles"("id") on delete cascade on update cascade
);
CREATE UNIQUE INDEX "insurance_policies_provider_policy_number_unique" on "insurance_policies"(
  "provider",
  "policy_number"
);
CREATE INDEX "insurance_policies_contractor_profile_id_status_index" on "insurance_policies"(
  "contractor_profile_id",
  "status"
);
CREATE INDEX "insurance_policies_expiry_date_index" on "insurance_policies"(
  "expiry_date"
);
CREATE INDEX "insurance_policies_status_index" on "insurance_policies"(
  "status"
);
CREATE TABLE IF NOT EXISTS "compliance_records"(
  "id" integer primary key autoincrement not null,
  "contractor_profile_id" integer not null,
  "compliance_type_id" integer not null,
  "compliance_status_id" integer not null,
  "findings" text,
  "inspection_date" date,
  "next_review_date" date,
  "inspected_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("contractor_profile_id") references "contractor_profiles"("id") on delete cascade on update cascade,
  foreign key("compliance_type_id") references "compliance_types"("id") on delete restrict on update cascade,
  foreign key("compliance_status_id") references "compliance_statuses"("id") on delete restrict on update cascade,
  foreign key("inspected_by") references "users"("id") on delete set null
);
CREATE INDEX "compliance_records_profile_type_idx" on "compliance_records"(
  "contractor_profile_id",
  "compliance_type_id"
);
CREATE INDEX "compliance_records_status_date_idx" on "compliance_records"(
  "compliance_status_id",
  "inspection_date"
);
CREATE INDEX "compliance_records_inspection_date_index" on "compliance_records"(
  "inspection_date"
);
CREATE INDEX "compliance_records_next_review_date_index" on "compliance_records"(
  "next_review_date"
);
CREATE TABLE IF NOT EXISTS "legal_cases"(
  "id" integer primary key autoincrement not null,
  "contractor_profile_id" integer not null,
  "case_number" varchar not null,
  "court" varchar not null,
  "status" varchar not null,
  "filing_date" date,
  "resolution_date" date,
  "summary" text,
  "is_confidential" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("contractor_profile_id") references "contractor_profiles"("id") on delete cascade on update cascade
);
CREATE UNIQUE INDEX "legal_cases_court_case_number_unique" on "legal_cases"(
  "court",
  "case_number"
);
CREATE INDEX "legal_cases_contractor_profile_id_status_index" on "legal_cases"(
  "contractor_profile_id",
  "status"
);
CREATE INDEX "legal_cases_status_index" on "legal_cases"("status");
CREATE INDEX "legal_cases_filing_date_index" on "legal_cases"("filing_date");
CREATE INDEX "legal_cases_is_confidential_index" on "legal_cases"(
  "is_confidential"
);
CREATE TABLE IF NOT EXISTS "blacklist_histories"(
  "id" integer primary key autoincrement not null,
  "contractor_profile_id" integer not null,
  "started_on" date not null,
  "ended_on" date,
  "reason" text not null,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("contractor_profile_id") references "contractor_profiles"("id") on delete cascade on update cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "blacklist_histories_contractor_profile_id_started_on_index" on "blacklist_histories"(
  "contractor_profile_id",
  "started_on"
);
CREATE INDEX "blacklist_histories_started_on_index" on "blacklist_histories"(
  "started_on"
);
CREATE INDEX "blacklist_histories_ended_on_index" on "blacklist_histories"(
  "ended_on"
);
CREATE TABLE IF NOT EXISTS "contractor_performance_snapshots"(
  "id" integer primary key autoincrement not null,
  "contractor_profile_id" integer not null,
  "project_id" integer not null,
  "contract_id" integer,
  "agency_id" integer not null,
  "budget_id" integer,
  "planned_completion_date" date,
  "actual_completion_date" date,
  "delay_days" integer not null default '0',
  "final_cost" numeric not null default '0',
  "cost_variance" numeric not null default '0',
  "quality_rating" integer not null default '0',
  "agency_evaluation" integer not null default '0',
  "completion_status" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("contractor_profile_id") references "contractor_profiles"("id") on delete cascade on update cascade,
  foreign key("project_id") references "projects"("id") on delete restrict on update cascade,
  foreign key("contract_id") references "contracts"("id") on delete set null,
  foreign key("agency_id") references "agencies"("id") on delete restrict on update cascade,
  foreign key("budget_id") references "budgets"("id") on delete set null
);
CREATE INDEX "contractor_snapshots_profile_status_idx" on "contractor_performance_snapshots"(
  "contractor_profile_id",
  "completion_status"
);
CREATE INDEX "contractor_snapshots_project_agency_idx" on "contractor_performance_snapshots"(
  "project_id",
  "agency_id"
);
CREATE INDEX "contractor_performance_snapshots_completion_status_index" on "contractor_performance_snapshots"(
  "completion_status"
);
CREATE TABLE IF NOT EXISTS "contractor_activities"(
  "id" integer primary key autoincrement not null,
  "contractor_profile_id" integer,
  "organization_id" integer,
  "contractor_activity_type_id" integer,
  "actor_id" integer,
  "event" varchar not null,
  "description" text,
  "old_values" text,
  "new_values" text,
  "ip_address" varchar,
  "user_agent" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("contractor_profile_id") references "contractor_profiles"("id") on delete set null,
  foreign key("organization_id") references "organizations"("id") on delete set null,
  foreign key("contractor_activity_type_id") references "contractor_activity_types"("id") on delete set null,
  foreign key("actor_id") references "users"("id") on delete set null
);
CREATE INDEX "contractor_activities_profile_event_idx" on "contractor_activities"(
  "contractor_profile_id",
  "event"
);
CREATE INDEX "contractor_activities_org_event_idx" on "contractor_activities"(
  "organization_id",
  "event"
);
CREATE INDEX "contractor_activities_event_index" on "contractor_activities"(
  "event"
);
CREATE TABLE IF NOT EXISTS "document_types"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "document_types_slug_unique" on "document_types"("slug");
CREATE INDEX "document_types_is_active_index" on "document_types"("is_active");
CREATE TABLE IF NOT EXISTS "document_categories"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "document_categories_slug_unique" on "document_categories"(
  "slug"
);
CREATE INDEX "document_categories_is_active_index" on "document_categories"(
  "is_active"
);
CREATE TABLE IF NOT EXISTS "document_statuses"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "document_statuses_slug_unique" on "document_statuses"(
  "slug"
);
CREATE INDEX "document_statuses_is_active_index" on "document_statuses"(
  "is_active"
);
CREATE TABLE IF NOT EXISTS "document_visibilities"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "document_visibilities_slug_unique" on "document_visibilities"(
  "slug"
);
CREATE INDEX "document_visibilities_is_active_index" on "document_visibilities"(
  "is_active"
);
CREATE TABLE IF NOT EXISTS "document_permission_types"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "document_permission_types_slug_unique" on "document_permission_types"(
  "slug"
);
CREATE INDEX "document_permission_types_is_active_index" on "document_permission_types"(
  "is_active"
);
CREATE TABLE IF NOT EXISTS "document_tags"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "document_tags_slug_unique" on "document_tags"("slug");
CREATE TABLE IF NOT EXISTS "documents"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "document_type_id" integer not null,
  "document_category_id" integer,
  "document_status_id" integer not null,
  "document_visibility_id" integer not null,
  "title" varchar not null,
  "description" text,
  "original_filename" varchar not null,
  "stored_filename" varchar not null,
  "storage_disk" varchar not null,
  "storage_path" varchar not null,
  "file_extension" varchar not null,
  "mime_type" varchar not null,
  "checksum" varchar not null,
  "file_size" integer not null default '0',
  "page_count" integer,
  "language" varchar,
  "owner_id" integer,
  "uploaded_by" integer,
  "version_number" integer not null default '1',
  "ocr_status" varchar not null default 'pending',
  "index_status" varchar not null default 'pending',
  "preview_status" varchar not null default 'pending',
  "thumbnail_path" varchar,
  "archived_at" datetime,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("document_type_id") references "document_types"("id") on delete restrict on update cascade,
  foreign key("document_category_id") references "document_categories"("id") on delete set null,
  foreign key("document_status_id") references "document_statuses"("id") on delete restrict on update cascade,
  foreign key("document_visibility_id") references "document_visibilities"("id") on delete restrict on update cascade,
  foreign key("owner_id") references "users"("id") on delete set null,
  foreign key("uploaded_by") references "users"("id") on delete set null,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "documents_type_status_idx" on "documents"(
  "document_type_id",
  "document_status_id"
);
CREATE INDEX "documents_category_visibility_idx" on "documents"(
  "document_category_id",
  "document_visibility_id"
);
CREATE INDEX "documents_owner_uploader_idx" on "documents"(
  "owner_id",
  "uploaded_by"
);
CREATE INDEX "documents_created_size_idx" on "documents"(
  "created_at",
  "file_size"
);
CREATE UNIQUE INDEX "documents_uuid_unique" on "documents"("uuid");
CREATE INDEX "documents_file_extension_index" on "documents"("file_extension");
CREATE INDEX "documents_mime_type_index" on "documents"("mime_type");
CREATE INDEX "documents_checksum_index" on "documents"("checksum");
CREATE INDEX "documents_language_index" on "documents"("language");
CREATE INDEX "documents_ocr_status_index" on "documents"("ocr_status");
CREATE INDEX "documents_index_status_index" on "documents"("index_status");
CREATE INDEX "documents_preview_status_index" on "documents"("preview_status");
CREATE INDEX "documents_archived_at_index" on "documents"("archived_at");
CREATE TABLE IF NOT EXISTS "document_versions"(
  "id" integer primary key autoincrement not null,
  "document_id" integer not null,
  "version_number" integer not null,
  "original_filename" varchar not null,
  "stored_filename" varchar not null,
  "storage_disk" varchar not null,
  "storage_path" varchar not null,
  "file_extension" varchar not null,
  "mime_type" varchar not null,
  "checksum" varchar not null,
  "file_size" integer not null default '0',
  "uploaded_by" integer,
  "reason" text,
  "is_current" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("document_id") references "documents"("id") on delete cascade on update cascade,
  foreign key("uploaded_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "document_versions_document_id_version_number_unique" on "document_versions"(
  "document_id",
  "version_number"
);
CREATE INDEX "document_versions_document_id_is_current_index" on "document_versions"(
  "document_id",
  "is_current"
);
CREATE INDEX "document_versions_checksum_index" on "document_versions"(
  "checksum"
);
CREATE INDEX "document_versions_is_current_index" on "document_versions"(
  "is_current"
);
CREATE TABLE IF NOT EXISTS "documentables"(
  "id" integer primary key autoincrement not null,
  "document_id" integer not null,
  "documentable_type" varchar not null,
  "documentable_id" integer not null,
  "relationship_type" varchar not null default 'supporting',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("document_id") references "documents"("id") on delete cascade on update cascade
);
CREATE INDEX "documentables_documentable_type_documentable_id_index" on "documentables"(
  "documentable_type",
  "documentable_id"
);
CREATE UNIQUE INDEX "documentables_unique_link" on "documentables"(
  "document_id",
  "documentable_type",
  "documentable_id"
);
CREATE INDEX "documentables_relationship_type_index" on "documentables"(
  "relationship_type"
);
CREATE TABLE IF NOT EXISTS "document_tag"(
  "id" integer primary key autoincrement not null,
  "document_id" integer not null,
  "document_tag_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("document_id") references "documents"("id") on delete cascade on update cascade,
  foreign key("document_tag_id") references "document_tags"("id") on delete cascade on update cascade
);
CREATE UNIQUE INDEX "document_tag_document_id_document_tag_id_unique" on "document_tag"(
  "document_id",
  "document_tag_id"
);
CREATE TABLE IF NOT EXISTS "document_permissions"(
  "id" integer primary key autoincrement not null,
  "document_id" integer not null,
  "document_permission_type_id" integer not null,
  "role_id" integer,
  "user_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("document_id") references "documents"("id") on delete cascade on update cascade,
  foreign key("document_permission_type_id") references "document_permission_types"("id") on delete restrict on update cascade,
  foreign key("role_id") references "roles"("id") on delete cascade on update cascade,
  foreign key("user_id") references "users"("id") on delete cascade on update cascade
);
CREATE INDEX "document_permissions_document_id_role_id_index" on "document_permissions"(
  "document_id",
  "role_id"
);
CREATE INDEX "document_permissions_document_id_user_id_index" on "document_permissions"(
  "document_id",
  "user_id"
);
CREATE TABLE IF NOT EXISTS "document_activities"(
  "id" integer primary key autoincrement not null,
  "document_id" integer not null,
  "document_version_id" integer,
  "actor_id" integer,
  "event" varchar not null,
  "description" text,
  "old_values" text,
  "new_values" text,
  "ip_address" varchar,
  "user_agent" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("document_id") references "documents"("id") on delete cascade on update cascade,
  foreign key("document_version_id") references "document_versions"("id") on delete set null,
  foreign key("actor_id") references "users"("id") on delete set null
);
CREATE INDEX "document_activities_document_id_event_index" on "document_activities"(
  "document_id",
  "event"
);
CREATE INDEX "document_activities_actor_id_event_index" on "document_activities"(
  "actor_id",
  "event"
);
CREATE INDEX "document_activities_event_index" on "document_activities"(
  "event"
);
CREATE TABLE IF NOT EXISTS "document_ocr_metadata"(
  "id" integer primary key autoincrement not null,
  "document_id" integer not null,
  "extracted_text" text,
  "confidence" numeric,
  "language" varchar,
  "ocr_engine" varchar,
  "processing_time_ms" integer,
  "indexed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("document_id") references "documents"("id") on delete cascade on update cascade
);
CREATE UNIQUE INDEX "document_ocr_metadata_document_id_unique" on "document_ocr_metadata"(
  "document_id"
);
CREATE TABLE IF NOT EXISTS "document_ai_metadata"(
  "id" integer primary key autoincrement not null,
  "document_id" integer not null,
  "embedding_id" varchar,
  "vector_reference" varchar,
  "summary" text,
  "keywords" text,
  "entities" text,
  "topics" text,
  "last_ai_review_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("document_id") references "documents"("id") on delete cascade on update cascade
);
CREATE UNIQUE INDEX "document_ai_metadata_document_id_unique" on "document_ai_metadata"(
  "document_id"
);
CREATE TABLE IF NOT EXISTS "search_indexes"(
  "id" integer primary key autoincrement not null,
  "searchable_type" varchar not null,
  "searchable_id" integer not null,
  "module" varchar not null,
  "title" varchar not null,
  "description" text,
  "url" varchar,
  "visibility" varchar not null default 'internal',
  "status" varchar,
  "search_text" text,
  "embedding_reference" varchar,
  "semantic_hash" varchar,
  "entity_summary" text,
  "search_vector" text,
  "entity_keywords" text,
  "metadata" text,
  "last_embedding_update" datetime,
  "indexed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "search_indexes_searchable_type_searchable_id_unique" on "search_indexes"(
  "searchable_type",
  "searchable_id"
);
CREATE INDEX "search_indexes_module_visibility_status_index" on "search_indexes"(
  "module",
  "visibility",
  "status"
);
CREATE INDEX "search_indexes_searchable_type_searchable_id_index" on "search_indexes"(
  "searchable_type",
  "searchable_id"
);
CREATE INDEX "search_indexes_module_index" on "search_indexes"("module");
CREATE INDEX "search_indexes_title_index" on "search_indexes"("title");
CREATE INDEX "search_indexes_visibility_index" on "search_indexes"(
  "visibility"
);
CREATE INDEX "search_indexes_status_index" on "search_indexes"("status");
CREATE INDEX "search_indexes_semantic_hash_index" on "search_indexes"(
  "semantic_hash"
);
CREATE INDEX "search_indexes_indexed_at_index" on "search_indexes"(
  "indexed_at"
);
CREATE TABLE IF NOT EXISTS "search_documents"(
  "id" integer primary key autoincrement not null,
  "search_index_id" integer not null,
  "locale" varchar not null default 'en',
  "title" varchar not null,
  "excerpt" text,
  "body_hash" varchar,
  "indexed_payload" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("search_index_id") references "search_indexes"("id") on delete cascade
);
CREATE UNIQUE INDEX "search_documents_search_index_id_locale_unique" on "search_documents"(
  "search_index_id",
  "locale"
);
CREATE INDEX "search_documents_body_hash_index" on "search_documents"(
  "body_hash"
);
CREATE TABLE IF NOT EXISTS "search_keywords"(
  "id" integer primary key autoincrement not null,
  "search_index_id" integer not null,
  "keyword" varchar not null,
  "weight" integer not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("search_index_id") references "search_indexes"("id") on delete cascade
);
CREATE UNIQUE INDEX "search_keywords_search_index_id_keyword_unique" on "search_keywords"(
  "search_index_id",
  "keyword"
);
CREATE INDEX "search_keywords_keyword_index" on "search_keywords"("keyword");
CREATE TABLE IF NOT EXISTS "search_synonyms"(
  "id" integer primary key autoincrement not null,
  "term" varchar not null,
  "synonym" varchar not null,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "search_synonyms_term_synonym_unique" on "search_synonyms"(
  "term",
  "synonym"
);
CREATE INDEX "search_synonyms_term_index" on "search_synonyms"("term");
CREATE INDEX "search_synonyms_synonym_index" on "search_synonyms"("synonym");
CREATE INDEX "search_synonyms_is_active_index" on "search_synonyms"(
  "is_active"
);
CREATE TABLE IF NOT EXISTS "search_popularity"(
  "id" integer primary key autoincrement not null,
  "search_index_id" integer not null,
  "searches_count" integer not null default '0',
  "clicks_count" integer not null default '0',
  "popularity_score" numeric not null default '0',
  "last_clicked_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("search_index_id") references "search_indexes"("id") on delete cascade
);
CREATE UNIQUE INDEX "search_popularity_search_index_id_unique" on "search_popularity"(
  "search_index_id"
);
CREATE TABLE IF NOT EXISTS "search_clicks"(
  "id" integer primary key autoincrement not null,
  "search_index_id" integer,
  "user_id" integer,
  "query" varchar,
  "result_position" integer not null default '0',
  "clicked_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("search_index_id") references "search_indexes"("id") on delete set null,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE INDEX "search_clicks_query_index" on "search_clicks"("query");
CREATE INDEX "search_clicks_clicked_at_index" on "search_clicks"("clicked_at");
CREATE TABLE IF NOT EXISTS "saved_searches"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "name" varchar not null,
  "query" varchar,
  "module" varchar,
  "filters" text,
  "sort" varchar not null default 'relevance',
  "direction" varchar not null default 'desc',
  "is_default" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE INDEX "saved_searches_user_id_module_index" on "saved_searches"(
  "user_id",
  "module"
);
CREATE INDEX "saved_searches_module_index" on "saved_searches"("module");
CREATE TABLE IF NOT EXISTS "search_history"(
  "id" integer primary key autoincrement not null,
  "user_id" integer,
  "query" varchar,
  "module" varchar,
  "filters" text,
  "results_count" integer not null default '0',
  "latency_ms" integer not null default '0',
  "successful" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE INDEX "search_history_user_id_created_at_index" on "search_history"(
  "user_id",
  "created_at"
);
CREATE INDEX "search_history_query_index" on "search_history"("query");
CREATE INDEX "search_history_module_index" on "search_history"("module");
CREATE INDEX "search_history_successful_index" on "search_history"(
  "successful"
);
CREATE TABLE IF NOT EXISTS "search_jobs"(
  "id" integer primary key autoincrement not null,
  "search_index_id" integer,
  "searchable_type" varchar,
  "searchable_id" integer,
  "operation" varchar not null,
  "status" varchar not null default 'queued',
  "attempts" integer not null default '0',
  "queued_at" datetime,
  "processed_at" datetime,
  "error_message" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("search_index_id") references "search_indexes"("id") on delete set null
);
CREATE INDEX "search_jobs_searchable_type_searchable_id_index" on "search_jobs"(
  "searchable_type",
  "searchable_id"
);
CREATE INDEX "search_jobs_operation_index" on "search_jobs"("operation");
CREATE INDEX "search_jobs_status_index" on "search_jobs"("status");
CREATE TABLE IF NOT EXISTS "analytics_snapshot_periods"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "analytics_snapshot_periods_slug_unique" on "analytics_snapshot_periods"(
  "slug"
);
CREATE TABLE IF NOT EXISTS "analytics_snapshots"(
  "id" integer primary key autoincrement not null,
  "analytics_snapshot_period_id" integer not null,
  "dashboard" varchar not null,
  "snapshot_date" date not null,
  "filter_hash" varchar not null,
  "filters" text,
  "metrics" text not null,
  "charts" text,
  "insights" text,
  "generated_by" integer,
  "generated_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("analytics_snapshot_period_id") references "analytics_snapshot_periods"("id") on delete restrict,
  foreign key("generated_by") references "users"("id") on delete set null
);
CREATE INDEX "analytics_snapshot_period_dashboard_date_index" on "analytics_snapshots"(
  "analytics_snapshot_period_id",
  "dashboard",
  "snapshot_date"
);
CREATE INDEX "analytics_snapshots_dashboard_index" on "analytics_snapshots"(
  "dashboard"
);
CREATE INDEX "analytics_snapshots_snapshot_date_index" on "analytics_snapshots"(
  "snapshot_date"
);
CREATE INDEX "analytics_snapshots_filter_hash_index" on "analytics_snapshots"(
  "filter_hash"
);
CREATE TABLE IF NOT EXISTS "analytics_reports"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "user_id" integer,
  "name" varchar,
  "dashboard" varchar not null,
  "format" varchar not null,
  "status" varchar not null default 'generated',
  "filters" text,
  "payload" text,
  "storage_disk" varchar,
  "storage_path" varchar,
  "generated_at" datetime,
  "expires_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "analytics_reports_uuid_unique" on "analytics_reports"(
  "uuid"
);
CREATE INDEX "analytics_reports_dashboard_index" on "analytics_reports"(
  "dashboard"
);
CREATE INDEX "analytics_reports_format_index" on "analytics_reports"("format");
CREATE INDEX "analytics_reports_status_index" on "analytics_reports"("status");
CREATE TABLE IF NOT EXISTS "analytics_alert_rules"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "category" varchar not null,
  "metric_key" varchar not null,
  "operator" varchar not null,
  "threshold" numeric not null,
  "severity" varchar not null,
  "message_template" text not null,
  "is_active" tinyint(1) not null default '1',
  "sort_order" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "analytics_alert_rules_slug_unique" on "analytics_alert_rules"(
  "slug"
);
CREATE INDEX "analytics_alert_rules_category_index" on "analytics_alert_rules"(
  "category"
);
CREATE INDEX "analytics_alert_rules_metric_key_index" on "analytics_alert_rules"(
  "metric_key"
);
CREATE INDEX "analytics_alert_rules_severity_index" on "analytics_alert_rules"(
  "severity"
);
CREATE INDEX "analytics_alert_rules_is_active_index" on "analytics_alert_rules"(
  "is_active"
);
CREATE TABLE IF NOT EXISTS "analytics_alerts"(
  "id" integer primary key autoincrement not null,
  "analytics_alert_rule_id" integer,
  "title" varchar not null,
  "message" text not null,
  "severity" varchar not null,
  "status" varchar not null default 'open',
  "triggered_value" numeric,
  "context" text,
  "triggered_at" datetime not null,
  "acknowledged_by" integer,
  "acknowledged_at" datetime,
  "resolved_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("analytics_alert_rule_id") references "analytics_alert_rules"("id") on delete set null,
  foreign key("acknowledged_by") references "users"("id") on delete set null
);
CREATE INDEX "analytics_alert_rule_status_index" on "analytics_alerts"(
  "analytics_alert_rule_id",
  "status"
);
CREATE INDEX "analytics_alerts_severity_index" on "analytics_alerts"(
  "severity"
);
CREATE INDEX "analytics_alerts_status_index" on "analytics_alerts"("status");
CREATE INDEX "analytics_alerts_triggered_at_index" on "analytics_alerts"(
  "triggered_at"
);
CREATE TABLE IF NOT EXISTS "dashboard_states"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "name" varchar not null,
  "dashboard" varchar not null,
  "filters" text,
  "is_default" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE INDEX "dashboard_states_user_id_dashboard_index" on "dashboard_states"(
  "user_id",
  "dashboard"
);
CREATE INDEX "dashboard_states_dashboard_index" on "dashboard_states"(
  "dashboard"
);
CREATE TABLE IF NOT EXISTS "analytics_events"(
  "id" integer primary key autoincrement not null,
  "user_id" integer,
  "event" varchar not null,
  "dashboard" varchar,
  "subject_type" varchar,
  "subject_id" integer,
  "payload" text,
  "occurred_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE INDEX "analytics_events_subject_type_subject_id_index" on "analytics_events"(
  "subject_type",
  "subject_id"
);
CREATE INDEX "analytics_events_event_index" on "analytics_events"("event");
CREATE INDEX "analytics_events_dashboard_index" on "analytics_events"(
  "dashboard"
);
CREATE INDEX "analytics_events_occurred_at_index" on "analytics_events"(
  "occurred_at"
);
CREATE TABLE IF NOT EXISTS "intelligence_rule_types"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "intelligence_rule_types_slug_unique" on "intelligence_rule_types"(
  "slug"
);
CREATE TABLE IF NOT EXISTS "intelligence_rules"(
  "id" integer primary key autoincrement not null,
  "intelligence_rule_type_id" integer not null,
  "name" varchar not null,
  "slug" varchar not null,
  "module" varchar not null,
  "category" varchar not null,
  "severity_default" varchar not null default 'info',
  "thresholds" text,
  "configuration" text,
  "version" varchar not null default '1.0.0',
  "is_active" tinyint(1) not null default '1',
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "description" text,
  "priority" integer not null default '100',
  "weight" integer not null default '50',
  "execution_frequency" varchar not null default 'manual',
  "documentation_url" varchar,
  "last_executed_at" datetime,
  "last_execution_ms" integer,
  foreign key("intelligence_rule_type_id") references "intelligence_rule_types"("id") on delete restrict on update cascade,
  foreign key("created_by") references "users"("id") on delete set null,
  foreign key("updated_by") references "users"("id") on delete set null
);
CREATE INDEX "intelligence_rules_module_is_active_index" on "intelligence_rules"(
  "module",
  "is_active"
);
CREATE INDEX "intelligence_rules_category_severity_default_index" on "intelligence_rules"(
  "category",
  "severity_default"
);
CREATE UNIQUE INDEX "intelligence_rules_slug_unique" on "intelligence_rules"(
  "slug"
);
CREATE INDEX "intelligence_rules_module_index" on "intelligence_rules"(
  "module"
);
CREATE INDEX "intelligence_rules_category_index" on "intelligence_rules"(
  "category"
);
CREATE INDEX "intelligence_rules_severity_default_index" on "intelligence_rules"(
  "severity_default"
);
CREATE INDEX "intelligence_rules_is_active_index" on "intelligence_rules"(
  "is_active"
);
CREATE TABLE IF NOT EXISTS "intelligence_evidence"(
  "id" integer primary key autoincrement not null,
  "intelligence_indicator_id" integer not null,
  "evidenceable_type" varchar,
  "evidenceable_id" integer,
  "label" varchar not null,
  "summary" text,
  "weight" integer not null default '50',
  "payload" text,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("intelligence_indicator_id") references "intelligence_indicators"("id") on delete restrict on update cascade,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE INDEX "intelligence_evidence_evidenceable_type_evidenceable_id_index" on "intelligence_evidence"(
  "evidenceable_type",
  "evidenceable_id"
);
CREATE INDEX "intelligence_evidence_intelligence_indicator_id_weight_index" on "intelligence_evidence"(
  "intelligence_indicator_id",
  "weight"
);
CREATE TABLE IF NOT EXISTS "intelligence_reviews"(
  "id" integer primary key autoincrement not null,
  "intelligence_indicator_id" integer not null,
  "reviewed_by" integer not null,
  "status" varchar not null,
  "notes" text,
  "reviewed_at" datetime not null,
  "follow_up_on" date,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("intelligence_indicator_id") references "intelligence_indicators"("id") on delete restrict on update cascade,
  foreign key("reviewed_by") references "users"("id") on delete restrict on update cascade
);
CREATE INDEX "intelligence_reviews_intelligence_indicator_id_status_index" on "intelligence_reviews"(
  "intelligence_indicator_id",
  "status"
);
CREATE INDEX "intelligence_reviews_status_index" on "intelligence_reviews"(
  "status"
);
CREATE INDEX "intelligence_reviews_reviewed_at_index" on "intelligence_reviews"(
  "reviewed_at"
);
CREATE INDEX "intelligence_reviews_follow_up_on_index" on "intelligence_reviews"(
  "follow_up_on"
);
CREATE TABLE IF NOT EXISTS "intelligence_processing_jobs"(
  "id" integer primary key autoincrement not null,
  "target_type" varchar,
  "target_id" integer,
  "job_type" varchar not null,
  "status" varchar not null default 'queued',
  "attempts" integer not null default '0',
  "payload" text,
  "error_summary" text,
  "queued_by" integer,
  "queued_at" datetime,
  "started_at" datetime,
  "completed_at" datetime,
  "failed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("queued_by") references "users"("id") on delete set null
);
CREATE INDEX "intelligence_processing_jobs_target_type_target_id_index" on "intelligence_processing_jobs"(
  "target_type",
  "target_id"
);
CREATE INDEX "intel_jobs_target_type_id_job_idx" on "intelligence_processing_jobs"(
  "target_type",
  "target_id",
  "job_type"
);
CREATE INDEX "intelligence_processing_jobs_status_queued_at_index" on "intelligence_processing_jobs"(
  "status",
  "queued_at"
);
CREATE INDEX "intelligence_processing_jobs_job_type_index" on "intelligence_processing_jobs"(
  "job_type"
);
CREATE INDEX "intelligence_processing_jobs_status_index" on "intelligence_processing_jobs"(
  "status"
);
CREATE INDEX "intelligence_processing_jobs_queued_at_index" on "intelligence_processing_jobs"(
  "queued_at"
);
CREATE TABLE IF NOT EXISTS "intelligence_activities"(
  "id" integer primary key autoincrement not null,
  "intelligence_indicator_id" integer,
  "intelligence_rule_id" integer,
  "intelligence_processing_job_id" integer,
  "actor_id" integer,
  "event" varchar not null,
  "description" text,
  "properties" text,
  "occurred_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("intelligence_indicator_id") references "intelligence_indicators"("id") on delete set null on update cascade,
  foreign key("intelligence_rule_id") references "intelligence_rules"("id") on delete set null on update cascade,
  foreign key("intelligence_processing_job_id") references "intelligence_processing_jobs"("id") on delete set null on update cascade,
  foreign key("actor_id") references "users"("id") on delete set null
);
CREATE INDEX "intelligence_activities_event_occurred_at_index" on "intelligence_activities"(
  "event",
  "occurred_at"
);
CREATE INDEX "intelligence_activities_event_index" on "intelligence_activities"(
  "event"
);
CREATE INDEX "intelligence_activities_occurred_at_index" on "intelligence_activities"(
  "occurred_at"
);
CREATE TABLE IF NOT EXISTS "citizen_report_categories"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "is_active" tinyint(1) not null default '1',
  "sort_order" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "citizen_report_categories_slug_unique" on "citizen_report_categories"(
  "slug"
);
CREATE INDEX "citizen_report_categories_is_active_index" on "citizen_report_categories"(
  "is_active"
);
CREATE TABLE IF NOT EXISTS "citizen_report_statuses"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "is_default" tinyint(1) not null default '0',
  "is_terminal" tinyint(1) not null default '0',
  "is_active" tinyint(1) not null default '1',
  "sort_order" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "citizen_report_statuses_slug_unique" on "citizen_report_statuses"(
  "slug"
);
CREATE INDEX "citizen_report_statuses_is_default_index" on "citizen_report_statuses"(
  "is_default"
);
CREATE INDEX "citizen_report_statuses_is_terminal_index" on "citizen_report_statuses"(
  "is_terminal"
);
CREATE INDEX "citizen_report_statuses_is_active_index" on "citizen_report_statuses"(
  "is_active"
);
CREATE TABLE IF NOT EXISTS "citizen_reports"(
  "id" integer primary key autoincrement not null,
  "public_uuid" varchar not null,
  "citizen_report_category_id" integer not null,
  "citizen_report_status_id" integer not null,
  "submitter_id" integer not null,
  "assigned_to" integer,
  "project_id" integer,
  "agency_id" integer,
  "document_id" integer,
  "country_id" integer,
  "division_id" integer,
  "district_id" integer,
  "upazila_id" integer,
  "union_id" integer,
  "ward_id" integer,
  "title" varchar not null,
  "description" text not null,
  "location_text" varchar,
  "contact_preference" varchar not null default 'email',
  "moderation_notes" text,
  "submitted_at" datetime,
  "resolved_at" datetime,
  "archived_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "attachment_disk" varchar,
  "attachment_path" varchar,
  "attachment_original_filename" varchar,
  "attachment_mime_type" varchar,
  "attachment_size" integer,
  foreign key("citizen_report_category_id") references "citizen_report_categories"("id") on delete restrict on update cascade,
  foreign key("citizen_report_status_id") references "citizen_report_statuses"("id") on delete restrict on update cascade,
  foreign key("submitter_id") references "users"("id") on delete restrict on update cascade,
  foreign key("assigned_to") references "users"("id") on delete set null on update cascade,
  foreign key("project_id") references "projects"("id") on delete set null on update cascade,
  foreign key("agency_id") references "agencies"("id") on delete set null on update cascade,
  foreign key("document_id") references "documents"("id") on delete set null on update cascade,
  foreign key("country_id") references "countries"("id") on delete set null on update cascade,
  foreign key("division_id") references "divisions"("id") on delete set null on update cascade,
  foreign key("district_id") references "districts"("id") on delete set null on update cascade,
  foreign key("upazila_id") references "upazilas"("id") on delete set null on update cascade,
  foreign key("union_id") references "unions"("id") on delete set null on update cascade,
  foreign key("ward_id") references "wards"("id") on delete set null on update cascade
);
CREATE INDEX "citizen_reports_citizen_report_status_id_submitted_at_index" on "citizen_reports"(
  "citizen_report_status_id",
  "submitted_at"
);
CREATE INDEX "citizen_reports_submitter_id_submitted_at_index" on "citizen_reports"(
  "submitter_id",
  "submitted_at"
);
CREATE INDEX "citizen_reports_project_id_citizen_report_status_id_index" on "citizen_reports"(
  "project_id",
  "citizen_report_status_id"
);
CREATE INDEX "citizen_reports_agency_id_citizen_report_status_id_index" on "citizen_reports"(
  "agency_id",
  "citizen_report_status_id"
);
CREATE UNIQUE INDEX "citizen_reports_public_uuid_unique" on "citizen_reports"(
  "public_uuid"
);
CREATE INDEX "citizen_reports_submitted_at_index" on "citizen_reports"(
  "submitted_at"
);
CREATE INDEX "citizen_reports_resolved_at_index" on "citizen_reports"(
  "resolved_at"
);
CREATE INDEX "citizen_reports_archived_at_index" on "citizen_reports"(
  "archived_at"
);
CREATE TABLE IF NOT EXISTS "citizen_report_activities"(
  "id" integer primary key autoincrement not null,
  "citizen_report_id" integer not null,
  "actor_id" integer,
  "event" varchar not null,
  "notes" text,
  "old_values" text,
  "new_values" text,
  "ip_address" varchar,
  "user_agent" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("citizen_report_id") references "citizen_reports"("id") on delete cascade on update cascade,
  foreign key("actor_id") references "users"("id") on delete set null on update cascade
);
CREATE INDEX "citizen_report_activities_citizen_report_id_event_index" on "citizen_report_activities"(
  "citizen_report_id",
  "event"
);
CREATE INDEX "citizen_report_activities_event_index" on "citizen_report_activities"(
  "event"
);
CREATE TABLE IF NOT EXISTS "bidder_organizations"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "registration_number" varchar,
  "contact_person" varchar,
  "email" varchar,
  "phone" varchar,
  "website" varchar,
  "address" text,
  "status" varchar not null default('active'),
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "organization_id" integer,
  foreign key("organization_id") references "organizations"("id") on delete set null
);
CREATE INDEX "bidder_organizations_registration_number_index" on "bidder_organizations"(
  "registration_number"
);
CREATE UNIQUE INDEX "bidder_organizations_slug_unique" on "bidder_organizations"(
  "slug"
);
CREATE INDEX "bidder_organizations_status_name_index" on "bidder_organizations"(
  "status",
  "name"
);
CREATE INDEX "bidder_org_contractor_registration_idx" on "bidder_organizations"(
  "organization_id",
  "registration_number"
);
CREATE TABLE IF NOT EXISTS "procurement_plans"(
  "id" integer primary key autoincrement not null,
  "plan_number" varchar not null,
  "title" varchar not null,
  "agency_id" integer not null,
  "budget_id" integer not null,
  "project_id" integer not null,
  "fiscal_year_id" integer not null,
  "funding_source_id" integer not null,
  "procurement_method_id" integer not null,
  "estimated_value" numeric not null,
  "priority" varchar not null default 'normal',
  "planned_start_date" date,
  "planned_award_date" date,
  "planned_completion_date" date,
  "status" varchar not null default 'draft',
  "approved_by" integer,
  "approved_at" datetime,
  "approval_notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("agency_id") references "agencies"("id") on delete restrict on update cascade,
  foreign key("budget_id") references "budgets"("id") on delete restrict on update cascade,
  foreign key("project_id") references "projects"("id") on delete restrict on update cascade,
  foreign key("fiscal_year_id") references "fiscal_years"("id") on delete restrict on update cascade,
  foreign key("funding_source_id") references "funding_sources"("id") on delete restrict on update cascade,
  foreign key("procurement_method_id") references "procurement_methods"("id") on delete restrict on update cascade,
  foreign key("approved_by") references "users"("id") on delete set null
);
CREATE INDEX "procurement_plans_agency_id_status_index" on "procurement_plans"(
  "agency_id",
  "status"
);
CREATE INDEX "procurement_plans_project_id_fiscal_year_id_index" on "procurement_plans"(
  "project_id",
  "fiscal_year_id"
);
CREATE INDEX "procurement_plans_procurement_method_id_priority_index" on "procurement_plans"(
  "procurement_method_id",
  "priority"
);
CREATE UNIQUE INDEX "procurement_plans_plan_number_unique" on "procurement_plans"(
  "plan_number"
);
CREATE INDEX "procurement_plans_priority_index" on "procurement_plans"(
  "priority"
);
CREATE INDEX "procurement_plans_status_index" on "procurement_plans"("status");
CREATE INDEX "procurement_plans_approved_at_index" on "procurement_plans"(
  "approved_at"
);
CREATE TABLE IF NOT EXISTS "procurement_plan_activities"(
  "id" integer primary key autoincrement not null,
  "procurement_plan_id" integer not null,
  "actor_id" integer,
  "event" varchar not null,
  "description" text,
  "old_values" text,
  "new_values" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("procurement_plan_id") references "procurement_plans"("id") on delete cascade on update cascade,
  foreign key("actor_id") references "users"("id") on delete set null
);
CREATE INDEX "proc_plan_activities_plan_created_idx" on "procurement_plan_activities"(
  "procurement_plan_id",
  "created_at"
);
CREATE INDEX "procurement_plan_activities_event_index" on "procurement_plan_activities"(
  "event"
);
CREATE INDEX "bid_submissions_opened_at_index" on "bid_submissions"(
  "opened_at"
);
CREATE INDEX "bid_submissions_withdrawn_at_index" on "bid_submissions"(
  "withdrawn_at"
);
CREATE TABLE IF NOT EXISTS "bid_opening_records"(
  "id" integer primary key autoincrement not null,
  "bid_submission_id" integer not null,
  "opened_by" integer,
  "opened_at" datetime not null,
  "recorded_amount" numeric,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("bid_submission_id") references "bid_submissions"("id") on delete cascade on update cascade,
  foreign key("opened_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "bid_opening_records_bid_submission_id_unique" on "bid_opening_records"(
  "bid_submission_id"
);
CREATE INDEX "bid_opening_records_opened_at_index" on "bid_opening_records"(
  "opened_at"
);
CREATE TABLE IF NOT EXISTS "bid_withdrawals"(
  "id" integer primary key autoincrement not null,
  "bid_submission_id" integer not null,
  "withdrawn_by" integer,
  "withdrawn_at" datetime not null,
  "reason" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("bid_submission_id") references "bid_submissions"("id") on delete cascade on update cascade,
  foreign key("withdrawn_by") references "users"("id") on delete set null
);
CREATE INDEX "bid_withdrawals_withdrawn_at_index" on "bid_withdrawals"(
  "withdrawn_at"
);
CREATE TABLE IF NOT EXISTS "bid_compliance_items"(
  "id" integer primary key autoincrement not null,
  "bid_submission_id" integer not null,
  "requirement" varchar not null,
  "status" varchar not null default 'pending',
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("bid_submission_id") references "bid_submissions"("id") on delete cascade on update cascade
);
CREATE INDEX "bid_compliance_items_status_index" on "bid_compliance_items"(
  "status"
);
CREATE TABLE IF NOT EXISTS "evaluation_summaries"(
  "id" integer primary key autoincrement not null,
  "bid_submission_id" integer not null,
  "technical_score" numeric not null default '0',
  "financial_score" numeric not null default '0',
  "compliance_score" numeric not null default '0',
  "overall_score" numeric not null default '0',
  "recommendation" text,
  "finalized_by" integer,
  "finalized_at" datetime not null,
  "score_payload" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("bid_submission_id") references "bid_submissions"("id") on delete cascade on update cascade,
  foreign key("finalized_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "evaluation_summaries_bid_submission_id_unique" on "evaluation_summaries"(
  "bid_submission_id"
);
CREATE INDEX "evaluation_summaries_finalized_at_index" on "evaluation_summaries"(
  "finalized_at"
);
CREATE TABLE IF NOT EXISTS "award_approvals"(
  "id" integer primary key autoincrement not null,
  "award_id" integer not null,
  "approved_by" integer,
  "status" varchar not null default 'approved',
  "notes" text,
  "approved_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("award_id") references "awards"("id") on delete cascade on update cascade,
  foreign key("approved_by") references "users"("id") on delete set null
);
CREATE INDEX "award_approvals_status_index" on "award_approvals"("status");
CREATE INDEX "award_approvals_approved_at_index" on "award_approvals"(
  "approved_at"
);
CREATE INDEX "awards_public_disclosure_status_index" on "awards"(
  "public_disclosure_status"
);
CREATE TABLE IF NOT EXISTS "contract_milestones"(
  "id" integer primary key autoincrement not null,
  "contract_id" integer not null,
  "title" varchar not null,
  "due_date" date,
  "completed_at" date,
  "status" varchar not null default('pending'),
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "completion_percentage" integer not null default '0',
  "accepted_by" integer,
  "accepted_at" datetime,
  "evidence_summary" text,
  foreign key("contract_id") references contracts("id") on delete cascade on update cascade,
  foreign key("accepted_by") references "users"("id") on delete set null
);
CREATE INDEX "contract_milestones_contract_id_status_index" on "contract_milestones"(
  "contract_id",
  "status"
);
CREATE INDEX "contract_milestones_accepted_at_index" on "contract_milestones"(
  "accepted_at"
);
CREATE TABLE IF NOT EXISTS "contract_deliverables"(
  "id" integer primary key autoincrement not null,
  "contract_id" integer not null,
  "contract_milestone_id" integer,
  "title" varchar not null,
  "description" text,
  "due_date" date,
  "accepted_at" datetime,
  "accepted_by" integer,
  "status" varchar not null default 'pending',
  "evidence_summary" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("contract_id") references "contracts"("id") on delete cascade on update cascade,
  foreign key("contract_milestone_id") references "contract_milestones"("id") on delete set null on update cascade,
  foreign key("accepted_by") references "users"("id") on delete set null
);
CREATE INDEX "contract_deliverables_accepted_at_index" on "contract_deliverables"(
  "accepted_at"
);
CREATE INDEX "contract_deliverables_status_index" on "contract_deliverables"(
  "status"
);
CREATE TABLE IF NOT EXISTS "contract_payments"(
  "id" integer primary key autoincrement not null,
  "contract_id" integer not null,
  "payment_reference" varchar not null,
  "amount" numeric not null,
  "paid_at" date,
  "status" varchar not null default 'pending',
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("contract_id") references "contracts"("id") on delete cascade on update cascade
);
CREATE UNIQUE INDEX "contract_payments_payment_reference_unique" on "contract_payments"(
  "payment_reference"
);
CREATE INDEX "contract_payments_paid_at_index" on "contract_payments"(
  "paid_at"
);
CREATE INDEX "contract_payments_status_index" on "contract_payments"("status");
CREATE TABLE IF NOT EXISTS "contract_closeouts"(
  "id" integer primary key autoincrement not null,
  "contract_id" integer not null,
  "closed_by" integer,
  "closed_at" datetime not null,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("contract_id") references "contracts"("id") on delete cascade on update cascade,
  foreign key("closed_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "contract_closeouts_contract_id_unique" on "contract_closeouts"(
  "contract_id"
);
CREATE INDEX "contract_closeouts_closed_at_index" on "contract_closeouts"(
  "closed_at"
);
CREATE TABLE IF NOT EXISTS "civic_intelligence_runs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "engine_version" varchar not null,
  "status" varchar not null default 'running',
  "triggered_by" integer,
  "started_at" datetime not null,
  "completed_at" datetime,
  "rules_executed" integer not null default '0',
  "indicators_created" integer not null default '0',
  "threshold_snapshot" text,
  "summary_payload" text,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("triggered_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "civic_intelligence_runs_uuid_unique" on "civic_intelligence_runs"(
  "uuid"
);
CREATE INDEX "civic_intelligence_runs_engine_version_index" on "civic_intelligence_runs"(
  "engine_version"
);
CREATE INDEX "civic_intelligence_runs_status_index" on "civic_intelligence_runs"(
  "status"
);
CREATE INDEX "civic_intelligence_runs_started_at_index" on "civic_intelligence_runs"(
  "started_at"
);
CREATE INDEX "civic_intelligence_runs_completed_at_index" on "civic_intelligence_runs"(
  "completed_at"
);
CREATE INDEX "intelligence_rules_priority_index" on "intelligence_rules"(
  "priority"
);
CREATE INDEX "intelligence_rules_execution_frequency_index" on "intelligence_rules"(
  "execution_frequency"
);
CREATE INDEX "intelligence_rules_last_executed_at_index" on "intelligence_rules"(
  "last_executed_at"
);
CREATE TABLE IF NOT EXISTS "intelligence_rule_audits"(
  "id" integer primary key autoincrement not null,
  "intelligence_rule_id" integer not null,
  "actor_id" integer,
  "event" varchar not null,
  "before" text,
  "after" text,
  "occurred_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("intelligence_rule_id") references "intelligence_rules"("id") on delete restrict on update cascade,
  foreign key("actor_id") references "users"("id") on delete set null
);
CREATE INDEX "intel_rule_audits_rule_occurred_idx" on "intelligence_rule_audits"(
  "intelligence_rule_id",
  "occurred_at"
);
CREATE INDEX "intelligence_rule_audits_event_index" on "intelligence_rule_audits"(
  "event"
);
CREATE INDEX "intelligence_rule_audits_occurred_at_index" on "intelligence_rule_audits"(
  "occurred_at"
);
CREATE TABLE IF NOT EXISTS "change_requests"(
  "id" integer primary key autoincrement not null,
  "requester_id" integer not null,
  "module" varchar not null,
  "operation" varchar not null default 'update',
  "subject_type" varchar,
  "subject_id" integer,
  "target_id" integer,
  "subject_label" varchar not null,
  "subject_url" varchar,
  "target_field" varchar,
  "current_value" text,
  "proposed_value" text,
  "summary" varchar not null,
  "details" text,
  "status" varchar not null default 'pending',
  "reviewer_id" integer,
  "reviewed_at" datetime,
  "review_notes" text,
  "metadata" text,
  "payload" text,
  "attachment_path" varchar,
  "attachment_name" varchar,
  "attachment_mime_type" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("requester_id") references "users"("id") on delete cascade,
  foreign key("reviewer_id") references "users"("id") on delete set null
);
CREATE INDEX "change_requests_module_status_index" on "change_requests"(
  "module",
  "status"
);
CREATE INDEX "change_requests_module_operation_status_index" on "change_requests"(
  "module",
  "operation",
  "status"
);
CREATE INDEX "change_requests_subject_type_subject_id_index" on "change_requests"(
  "subject_type",
  "subject_id"
);
CREATE TABLE IF NOT EXISTS "change_request_activities"(
  "id" integer primary key autoincrement not null,
  "change_request_id" integer not null,
  "actor_id" integer,
  "event" varchar not null,
  "notes" text,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("change_request_id") references "change_requests"("id") on delete cascade,
  foreign key("actor_id") references "users"("id") on delete set null
);
CREATE INDEX "change_request_activities_change_request_id_created_at_index" on "change_request_activities"(
  "change_request_id",
  "created_at"
);
CREATE TABLE IF NOT EXISTS "email_verification_otps"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "code_hash" varchar not null,
  "attempts" integer not null default '0',
  "expires_at" datetime not null,
  "last_sent_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "email_verification_otps_user_id_unique" on "email_verification_otps"(
  "user_id"
);
CREATE TABLE IF NOT EXISTS "intelligence_indicators"(
  "id" integer primary key autoincrement not null,
  "intelligence_rule_id" integer not null,
  "source_type" varchar,
  "source_id" integer,
  "module" varchar not null,
  "title" varchar not null,
  "description" text,
  "severity" varchar not null default('info'),
  "confidence_score" integer not null default('0'),
  "status" varchar not null default('pending'),
  "detected_at" datetime not null,
  "rule_version" varchar not null,
  "detection_payload" text,
  "metadata" text,
  "created_by" integer,
  "updated_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "civic_intelligence_run_id" integer,
  foreign key("updated_by") references users("id") on delete set null on update no action,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("intelligence_rule_id") references intelligence_rules("id") on delete restrict on update cascade,
  foreign key("civic_intelligence_run_id") references "civic_intelligence_runs"("id") on delete set null
);
CREATE INDEX "intelligence_indicators_detected_at_index" on "intelligence_indicators"(
  "detected_at"
);
CREATE INDEX "intelligence_indicators_module_index" on "intelligence_indicators"(
  "module"
);
CREATE INDEX "intelligence_indicators_module_severity_status_index" on "intelligence_indicators"(
  "module",
  "severity",
  "status"
);
CREATE INDEX "intelligence_indicators_severity_index" on "intelligence_indicators"(
  "severity"
);
CREATE INDEX "intelligence_indicators_source_type_source_id_index" on "intelligence_indicators"(
  "source_type",
  "source_id"
);
CREATE INDEX "intelligence_indicators_source_type_source_id_status_index" on "intelligence_indicators"(
  "source_type",
  "source_id",
  "status"
);
CREATE INDEX "intelligence_indicators_status_index" on "intelligence_indicators"(
  "status"
);
CREATE INDEX "intel_indicators_run_severity_detected_idx" on "intelligence_indicators"(
  "civic_intelligence_run_id",
  "severity",
  "detected_at"
);
CREATE INDEX "intel_runs_status_started_idx" on "civic_intelligence_runs"(
  "status",
  "started_at"
);
CREATE INDEX "projects_intel_delay_candidate_idx" on "projects"(
  "planned_end_date",
  "progress_percentage"
);
CREATE INDEX "awards_intel_status_bid_idx" on "awards"(
  "status",
  "bid_submission_id"
);
CREATE INDEX "citizen_reports_intel_open_project_idx" on "citizen_reports"(
  "resolved_at",
  "project_id"
);

INSERT INTO migrations VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(4,'2026_06_29_000001_create_roles_and_permissions_tables',1);
INSERT INTO migrations VALUES(5,'2026_06_29_000002_extend_users_for_identity_management',1);
INSERT INTO migrations VALUES(6,'2026_06_29_000003_create_permission_groups_table',1);
INSERT INTO migrations VALUES(7,'2026_06_29_000004_create_account_activities_table',1);
INSERT INTO migrations VALUES(8,'2026_06_29_000005_create_geographic_hierarchy_tables',1);
INSERT INTO migrations VALUES(9,'2026_06_29_000006_create_agency_registry_tables',1);
INSERT INTO migrations VALUES(10,'2026_06_29_000007_create_project_lifecycle_tables',1);
INSERT INTO migrations VALUES(11,'2026_06_29_000008_create_financial_management_tables',1);
INSERT INTO migrations VALUES(12,'2026_06_29_000009_remove_project_financial_amount_columns',1);
INSERT INTO migrations VALUES(13,'2026_06_29_000010_create_procurement_management_tables',1);
INSERT INTO migrations VALUES(14,'2026_06_30_000011_create_contractor_intelligence_tables',1);
INSERT INTO migrations VALUES(15,'2026_06_30_000012_create_document_management_tables',1);
INSERT INTO migrations VALUES(16,'2026_06_30_000013_create_universal_search_tables',1);
INSERT INTO migrations VALUES(17,'2026_07_01_000014_create_business_intelligence_tables',1);
INSERT INTO migrations VALUES(18,'2026_07_01_000015_create_intelligence_readiness_tables',1);
INSERT INTO migrations VALUES(19,'2026_07_01_000016_create_public_citizen_reporting_tables',1);
INSERT INTO migrations VALUES(20,'2026_07_01_000017_extend_procurement_enterprise_lifecycle',1);
INSERT INTO migrations VALUES(21,'2026_07_01_000018_create_civic_intelligence_runs_table',1);
INSERT INTO migrations VALUES(22,'2026_07_01_000019_extend_intelligence_rules_for_management',1);
INSERT INTO migrations VALUES(23,'2026_07_10_000001_add_attachment_columns_to_citizen_reports_table',1);
INSERT INTO migrations VALUES(24,'2026_07_10_000002_create_change_requests_table',1);
INSERT INTO migrations VALUES(25,'2026_07_10_000003_create_change_request_activities_table',1);
INSERT INTO migrations VALUES(26,'2026_07_10_000004_add_map_coordinates_to_agencies_and_organizations',1);
INSERT INTO migrations VALUES(27,'2026_07_10_000005_create_email_verification_otps_table',1);
INSERT INTO migrations VALUES(28,'2026_07_12_000001_add_location_point_to_projects_table',1);
INSERT INTO migrations VALUES(29,'2026_07_12_000002_add_intelligence_run_relationship_and_candidate_indexes',1);
