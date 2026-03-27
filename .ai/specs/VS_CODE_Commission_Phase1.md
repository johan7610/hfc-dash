BEFORE ANYTHING: Read these files completely:
- CLAUDE.md
- .ai/STANDARDS.md
- .ai/specs/commission_engine_spec.md

TASK: Build the Commission & Revenue Share Engine — Phase 1: Foundation

This is a NEW module. DO NOT modify any existing files except:
- routes/web.php (add new routes)
- corex-sidebar.blade.php (add navigation links)
- The User model (add new relationships only — do not change existing code)

== MIGRATION 1: commission_settings ==

CREATE TABLE commission_settings (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    agency_id BIGINT NOT NULL,
    commission_split_agent INT DEFAULT 80,
    commission_split_agency INT DEFAULT 20,
    annual_cap DECIMAL(12,2) DEFAULT 160000.00,
    post_cap_transaction_fee DECIMAL(10,2) DEFAULT 2500.00,
    post_cap_fee_cap DECIMAL(10,2) DEFAULT 50000.00,
    post_cap_reduced_fee DECIMAL(10,2) DEFAULT 750.00,
    monthly_platform_fee DECIMAL(10,2) DEFAULT 850.00,
    mentor_extra_split INT DEFAULT 20,
    mentor_transactions INT DEFAULT 3,
    risk_management_fee DECIMAL(10,2) DEFAULT 400.00,
    risk_management_cap DECIMAL(10,2) DEFAULT 5000.00,
    revenue_share_enabled BOOLEAN DEFAULT TRUE,
    revenue_share_pool_percent INT DEFAULT 50,
    tier_1_percent DECIMAL(5,2) DEFAULT 3.50,
    tier_2_percent DECIMAL(5,2) DEFAULT 4.00,
    tier_3_percent DECIMAL(5,2) DEFAULT 2.50,
    tier_4_percent DECIMAL(5,2) DEFAULT 1.50,
    tier_5_percent DECIMAL(5,2) DEFAULT 1.00,
    tier_6_percent DECIMAL(5,2) DEFAULT 0.50,
    tier_7_percent DECIMAL(5,2) DEFAULT 0.25,
    tier_4_flqa_requirement INT DEFAULT 5,
    tier_5_flqa_requirement INT DEFAULT 10,
    tier_6_flqa_requirement INT DEFAULT 15,
    tier_7_flqa_requirement INT DEFAULT 20,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

Seed one record for the first agency with all defaults.

== MIGRATION 2: agent_sponsorships ==

CREATE TABLE agent_sponsorships (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    agent_user_id BIGINT NOT NULL,
    sponsor_user_id BIGINT NOT NULL,
    sponsored_at DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(agent_user_id),
    INDEX(sponsor_user_id)
);

== MIGRATION 3: agent_cap_periods ==

CREATE TABLE agent_cap_periods (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    agency_id BIGINT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    cap_amount DECIMAL(12,2) NOT NULL,
    company_dollar_paid DECIMAL(12,2) DEFAULT 0.00,
    is_capped BOOLEAN DEFAULT FALSE,
    capped_at TIMESTAMP NULL,
    post_cap_fees_paid DECIMAL(10,2) DEFAULT 0.00,
    risk_fees_paid DECIMAL(10,2) DEFAULT 0.00,
    transactions_count INT DEFAULT 0,
    transactions_mentored INT DEFAULT 0,
    gross_commission_income DECIMAL(14,2) DEFAULT 0.00,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX(user_id, period_start)
);

== MIGRATION 4: commission_ledger ==

CREATE TABLE commission_ledger (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    agency_id BIGINT NOT NULL,
    cap_period_id BIGINT NOT NULL,
    deal_id BIGINT NULL,
    property_id BIGINT NULL,
    transaction_type ENUM('sale', 'rental_letting', 'rental_management', 'referral', 'other') NOT NULL,
    description VARCHAR(500) NOT NULL,
    gross_commission DECIMAL(12,2) NOT NULL,
    vat_amount DECIMAL(10,2) DEFAULT 0.00,
    commission_excl_vat DECIMAL(12,2) NOT NULL,
    agent_split_percent INT NOT NULL,
    agent_amount DECIMAL(12,2) NOT NULL,
    agency_amount DECIMAL(12,2) NOT NULL,
    transaction_fee DECIMAL(10,2) DEFAULT 0.00,
    risk_fee DECIMAL(10,2) DEFAULT 0.00,
    mentor_fee DECIMAL(10,2) DEFAULT 0.00,
    is_post_cap BOOLEAN DEFAULT FALSE,
    net_agent_amount DECIMAL(12,2) NOT NULL,
    company_dollar DECIMAL(12,2) NOT NULL,
    revenue_share_pool DECIMAL(12,2) DEFAULT 0.00,
    status ENUM('pending', 'confirmed', 'paid', 'cancelled') DEFAULT 'pending',
    deal_date DATE NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX(user_id, status),
    INDEX(agency_id, created_at)
);

== MIGRATION 5: revenue_share_ledger ==

CREATE TABLE revenue_share_ledger (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    commission_ledger_id BIGINT NOT NULL,
    producing_agent_id BIGINT NOT NULL,
    receiving_agent_id BIGINT NOT NULL,
    tier INT NOT NULL,
    company_dollar DECIMAL(12,2) NOT NULL,
    share_percent DECIMAL(5,2) NOT NULL,
    share_amount DECIMAL(10,2) NOT NULL,
    status ENUM('calculated', 'confirmed', 'paid') DEFAULT 'calculated',
    period_month DATE NOT NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX(receiving_agent_id, period_month),
    INDEX(producing_agent_id)
);

== MIGRATION 6: agent_mentors ==

CREATE TABLE agent_mentors (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    mentee_user_id BIGINT NOT NULL,
    mentor_user_id BIGINT NOT NULL,
    assigned_at DATE NOT NULL,
    graduated_at DATE NULL,
    transactions_completed INT DEFAULT 0,
    transactions_required INT DEFAULT 3,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(mentee_user_id)
);

== MIGRATION 7: Add columns to users table ==

Add these columns (all nullable, do NOT modify existing columns):
- anniversary_date DATE NULL
- sponsored_by_user_id BIGINT NULL
- agent_tier VARCHAR(20) DEFAULT 'standard'
- is_mentor_eligible BOOLEAN DEFAULT FALSE

== MODELS ==

Create these models in app/Models/ (or app/Models/Commission/ subfolder):

1. CommissionSetting — belongsTo Agency, singleton per agency
2. AgentSponsorship — belongsTo User (agent + sponsor), scopes: active()
3. AgentCapPeriod — belongsTo User + Agency, methods: 
   - recordCompanyDollar($amount) — adds to total, checks cap
   - checkCap() — returns true if capped
   - getRemainingToCap() — returns Rands remaining
   - getCurrentForUser($userId) — get or create current period
4. CommissionLedger — belongsTo User, Agency, AgentCapPeriod, SoftDeletes
   - calculateSplit($grossCommission, $vatAmount, $user) — calculates 
     the full split based on cap status, mentor status, fees
   - Scopes: pending(), confirmed(), paid(), thisMonth(), thisYear(), forUser($id)
5. RevenueShareLedger — belongsTo CommissionLedger, User (producing + receiving)
   - Scopes: forAgent($id), forMonth($date), pending()
6. AgentMentor — belongsTo User (mentee + mentor)
   - recordTransaction() — increments count, checks graduation
   - checkGraduation() — auto-graduates after required transactions

Add to User model (DO NOT change existing code, only ADD):
- sponsorship() HasOne AgentSponsorship (as agent)
- sponsor() — gets the sponsor User through sponsorship
- sponsoredAgents() — HasMany AgentSponsorship (as sponsor)
- currentCapPeriod() — gets or creates current AgentCapPeriod
- commissionEntries() HasMany CommissionLedger
- revenueShareReceived() HasMany RevenueShareLedger (as receiving_agent)
- mentorAssignment() HasOne AgentMentor (as mentee)
- mentees() HasMany AgentMentor (as mentor)
- isCapped() — checks current cap period
- isMentee() — checks active mentor assignment

== COMMISSION CALCULATION SERVICE ==

Create app/Services/CommissionCalculationService.php

Method: calculateDealCommission($userId, $grossCommission, $vatAmount, $transactionType, $description, $dealId = null, $propertyId = null)

Logic:
1. Get user's current cap period (create if doesn't exist)
2. Get agency commission settings
3. Check if user is capped
4. Check if user is in mentor program

If NOT capped and NOT mentored:
  - agent_amount = commission_excl_vat × (split_agent / 100)
  - agency_amount = commission_excl_vat - agent_amount
  - risk_fee = min(settings.risk_management_fee, settings.risk_management_cap - period.risk_fees_paid)
  - net_agent = agent_amount - risk_fee
  - company_dollar = agency_amount + risk_fee

If NOT capped and IS mentored:
  - Same as above but mentor_fee = commission_excl_vat × (mentor_extra_split / 100)
  - agent_amount reduced by mentor_fee
  - mentor gets half of mentor_fee, agency gets other half

If CAPPED:
  - Check if post_cap_fees_paid < post_cap_fee_cap
  - transaction_fee = settings.post_cap_transaction_fee (or reduced if over fee cap)
  - risk_fee = same logic as above
  - net_agent = commission_excl_vat - transaction_fee - risk_fee
  - company_dollar = transaction_fee + risk_fee

5. Create CommissionLedger record
6. Update AgentCapPeriod (company_dollar_paid, transactions_count, GCI)
7. Check if cap was just reached → mark is_capped, capped_at
8. Calculate revenue_share_pool = company_dollar × (revenue_share_pool_percent / 100)
9. If revenue_share_enabled → call distributeRevenueShare()
10. Return the CommissionLedger record

Method: distributeRevenueShare($commissionLedgerEntry)

Logic:
1. Get producing agent's sponsor chain (walk up the tree, max 7 levels)
2. For each level, check if the receiving agent qualifies:
   - Tiers 1-3: automatic
   - Tiers 4-7: check FLQA count meets requirement
3. Calculate share_amount = revenue_share_pool × tier_percent
4. Create RevenueShareLedger entry for each qualifying tier
5. Return total distributed

Method: getSponsorChain($userId, $maxDepth = 7)

Walk up the agent_sponsorships tree from the producing agent:
1. Get agent's sponsor (Tier 1 receiver)
2. Get sponsor's sponsor (Tier 2 receiver)
3. Continue up to maxDepth or until no more sponsors
4. Return array of [{user_id, tier}]

== SETTINGS UI ==

Create resources/views/corex/settings/commission.blade.php

A clean settings page under Settings → Commission & Revenue Share

Sections:
1. Commission Split — agent/agency percentage inputs (must total 100)
2. Cap Settings — annual cap, post-cap fees
3. Mentor Program — enable/disable, extra split %, transactions count
4. Monthly Fees — platform fee, risk fee, risk cap
5. Revenue Share — enable/disable, pool %, tier percentages (1-7), FLQA requirements
6. Save button

Use same CoreX styling as existing settings pages. Dark navy, teal accent.

== CONTROLLER ==

Create app/Http/Controllers/Commission/CommissionSettingsController.php
- edit() — show settings form
- update() — validate and save

== ROUTES ==

Add to web.php inside auth middleware group:

Route::get('settings/commission', [CommissionSettingsController::class, 'edit'])
    ->name('settings.commission');
Route::post('settings/commission', [CommissionSettingsController::class, 'update'])
    ->name('settings.commission.update');

== SIDEBAR ==

Add under Settings in corex-sidebar.blade.php:
- "Commission & Revenue Share" link to settings.commission

== IMPORTANT ==

- All money fields use DECIMAL(12,2) — never FLOAT
- VAT is 15% in South Africa
- All amounts in ZAR
- Use BCMath or simple multiplication for calculations — avoid floating point
- SoftDeletes on CommissionLedger
- Follow CoreX design standards for the settings page
- DO NOT modify any existing models except User (add relationships only)
- DO NOT modify any existing controllers or views except sidebar and web.php routes

AFTER ALL CHANGES:
1. php -l on ALL new PHP files
2. php artisan migrate
3. php artisan view:clear
4. Run scripts/dev-check.ps1 — must pass with 0 new failures
5. php artisan route:list | grep commission — show all routes
6. Verify in Tinker:
   - CommissionSetting::first() returns seeded defaults
   - New User relationships don't break existing queries
7. Report all new files, tables created, routes registered
8. Do not mark done until all pass
