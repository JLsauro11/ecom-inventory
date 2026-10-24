# E-commerce Inventory Management System V5

An upgrade of the supplied RS8 PHP/XAMPP application, preserving the existing database, aliases, mappings, uploads and history.

Platform logo update: bundled Shopee, Lazada and TikTok SVG wordmarks appear on the dashboard, sidebar, platform tabs, tables, allocation controls, transfers, generator, detail views and printable reports. Native dropdowns display the selected platform logo beside the control. All logos work offline.

Logout now asks for confirmation. Cancel keeps the current session; Ituloy ang Logout ends it. The dashboard and password page use the same prompt. Opening the logout URL directly displays a confirmation page; only a confirmed POST signs out.

All password fields have individual Show/Hide controls on login, password change, account creation and password reset. Passwords are hidden by default and after form reset.

Read **UPGRADE_V5.txt** before installing. Back up the live application and database, preserve the live `config.php`, `uploads` and `exports`, copy the application files, then sign in as Admin at `upgrade_v5.php` and run the additive migration. Do not use `install.php`, `reset_admin.php`, or import an old SQL backup for an existing installation.

V5 adds the approved red/dark RS8 styling, larger branding, threshold download/edit/upload with review and confirmation, corrected stock status queries, dashboard status links, protected receipt corrections and confirmation, formal outflow print summaries and original workbook PDF content previews. It also adds explicit non-sellable release links, paginated transaction history, validated atomic Beginning Balance changes with history, retained import errors, user role controls and forced password changes after account creation/reset.

The migration restores missing fields including `result_json` and `external_id`, adds history tables, and widens legacy order duplicate keys without rewriting them. Order imports recognize both new keys and historical posted orders. Confirmed receipt and threshold batches cannot be posted a second time.

See **TEST_REPORT_V5.txt** for validation results. Tests ran on an isolated database copied from the supplied backup; the live installation was not changed. Scanned receipt OCR requires manual review. Workbook PDF previews present all worksheets in formal paginated tables with repeated headers, readable dates, wrapped descriptions and file/batch details. Wide worksheets use linked column sections; original Excel formatting remains available in the original download. Historical non-sellable releases without reliable source links remain visibly unlinked.

Sign Up is available from the login screen. New accounts remain inactive and Pending Approval until an Admin assigns a role and approves them in Users / Settings > Registration Requests. Rejected users cannot sign in; Admins can reconsider a rejected request. Existing accounts keep their access. Run upgrade_v5.php to add the registration approval fields even if a previous V5 release was already installed.


Brand filters (2026-10-09): Current Stocks, Product Master and Platform Inventory support All Brands, the six requested brands and other brands present in active products. Filtering uses the recorded product Brand and does not alter records. Platform tabs retain Brand, search and status. No migration is required for this filter update.


Brand filters (2026-10-09): Current Stocks, Product Master and Platform Inventory support All Brands, the six requested brands and other brands present in active products. Filtering uses the recorded product Brand and does not alter records. Platform tabs retain Brand, search and status. No migration is required for this filter update.


Brand filters (2026-10-09): Current Stocks, Product Master and Platform Inventory support All Brands, the six requested brands and other brands present in active products. Filtering uses the recorded product Brand and does not alter records. Platform tabs retain Brand, search and status. No migration is required for this filter update.


Main/Subcategory update (2026-10-09): CVT (15), Engine (20), Performance Parts (9), Oils and Liquid Essentials (no subcategory). Run the updated upgrade_v5.php to add products.main_category. Existing categories are preserved with an unassigned Main Category until manually edited. Product creation/editing validates category pairs and preserves existing SKU/stock/mappings on edit. See Product_Category_Update_Guide.txt.


Receipt OCR/error history update (2026-10-09): image PDFs try OCR layouts 3 and 6, retain all detected rows for manual validation, and persist reading/line errors in Review Errors from Inflow History and Receipt Archive. Receipt header fields are manually correctable before posting. Error notifications remain visible. Failed authorized PDF/XLSX uploads are saved in history when the database is available; historical unsaved errors cannot be reconstructed.
