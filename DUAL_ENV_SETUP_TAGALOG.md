# E-commerce Inventory — dual-environment setup (XAMPP + Hostinger)

**Ito ay configuration-only PATCH. Walang live passwords, database dump, o user uploads sa package.** Hindi ito full project, at HINDI pa ito na-deploy sa Hostinger.

## Bakit ito kailangan?

Ang original `ecom.zip` ay may `config.php` na naglalaman ng production credentials. Ang `lib.php`, `install.php` at `update_check.php` ay lahat nagbabasa ng `config.php`. Kapag itinulak ang developer's local config bilang tracked `config.php`, puwedeng masira ang live connection kapag nag-auto-deploy.

Gamit ang patch:
- `config.php` — nasa Git; loader lamang, walang secrets.
- `config.runtime.php` — kaparehong filename pero **magkaibang laman** bawat machine/server, hindi nilalagay sa Git.
- `config.runtime.example.php` — sample para makapag-setup ang collaborators.
- `.gitignore` — pinipigilang aksidenteng ma-add ang `config.runtime.php` at future runtime files.

Walang detection base sa `localhost`, HTTP hostname, o account username; bawat installation ang may sarili nitong config.

## Ligtas na implementation sequence — huwag babaguhin ang order

1. **BACK UP LIVE:** i-export ang kasalukuyang Hostinger site files at *independently* ang live MySQL database. Siguraduhin may restore path. Huwag i-push ang `ecom.zip` sa public GitHub repo.
2. **CREATE LIVE RUNTIME CONFIG FIRST:** sa Hostinger, gumawa ng `config.runtime.php` sa parehong application root. Kopyahin ang **kasalukuyang LIVE `config.php` na nasa Hostinger** bilang laman nito nang walang pagbabago. Patunayan na valid at may access ang server; huwag itong i-upload sa GitHub. Mas mainam na ilagay ito outside `public_html` at i-set ang server environment variable `ECOM_CONFIG_FILE` sa absolute file path, kung supported sa hosting environment. **Kung inilipat ang runtime config outside the app root, ayusin ang `upload_dir` (at anumang path na gumagamit ng `__DIR__`) para tumuro pa rin sa tamang application directory**, dahil iba na ang `__DIR__` ng nilipat na file.
3. Kung `config.runtime.php` ay nakalagay inside web root, idagdag ang access denial sa kasalukuyang root `.htaccess` (append/merge, huwag papalitan ang existing rules):

   ```apache
   <Files "config.runtime.php">
     Require all denied
   </Files>
   ```

   Suriin din ang server configuration na epektibo ang denial; mas secure pa rin ang outside-webroot storage. Huwag mag-assume na lahat ng web servers ay gumagamit ng `.htaccess`.
4. **LOCAL COLLABORATOR:** pagkatapos ma-clone ang repo sa `C:\xampp\htdocs\ecom-inventory`, kopyahin ang `config.runtime.example.php` sa `config.runtime.php`, pagkatapos palitan ang DB name, DB user/password at tool paths sa LOCAL MySQL/XAMPP lamang. Gumamit ng hiwalay na dev database at dummy/test data. Huwag kumonekta sa production MySQL mula sa local.
5. **GITHUB:** add/commit ONLY `config.php`, `config.runtime.example.php`, at `.gitignore` (merge with existing `.gitignore` kung meron na). Siguraduhing HINDI kasama sa Changes list ang `config.runtime.php`, ZIP, SQL backup, o files sa uploads/exports. Verify `config.php` sa GitHub ay loader na lamang.
6. **STAGING FIRST:** i-deploy ang loader sa *test/staging environment* na may sarili ring `config.runtime.php`, at subukan ang login, DB connectivity, uploads, PDF/OCR, report, backups, at required DB migration bago production.
7. **LIVE DEPLOY:** kapag mayroon nang live runtime config at successful staging validation, saka ilapat ang new tracked `config.php` loader sa live. Verify HTTP 200, login, DB connection at key workflows. Kung failure, restore live files + database backup as appropriate.
8. **AUTO DEPLOY:** i-configure ang Hostinger na i-deploy lamang ang reviewed/merged `main` branch. Sa GitHub Desktop, collaborator creates a feature branch, commits, pushes, opens PR; only approved merges to `main` cause production deployment. Automatic deployment of code **does not** automatically migrate the database or sync uploads.

## Mahahalagang Git caveat

`.gitignore` ay may epekto lamang sa files na hindi pa tracked. Kung na-commit na dati ang `config.php` na may real production credentials, hindi sapat ang pag-delete ng file sa bagong commit: nananatili ito sa Git history. I-rotate agad ang anumang production password na nagkaroon ng public access at saka i-audit/clean ang Git history. Kung `uploads/` o `exports/` ay tracked na rin, huwag basta i-untrack at auto-deploy sa live nang walang server-side backup dahil ang pag-delete sa tracked index ay maaaring maapektuhan ang live files depende sa deployment strategy.

## Troubleshooting

- HTTP 503 + server log: check `config.runtime.php` exists (or `ECOM_CONFIG_FILE` points to it), readable by PHP.
- DB connection failure: check the values in that machine's own runtime file, database service, db privileges.
- PDF/OCR errors: Hostinger and Windows have different executables; set `pdftotext_path`, `pdftoppm_path`, `tesseract_path` on each environment as necessary.
- Installer/upgrade: do not run `install.php` or `reset_admin.php` against an existing live DB. Follow project upgrade guide and review schema migrations separately.
