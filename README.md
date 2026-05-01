# Forumino XF Deploy Repo

forumino.com test installation için **otomatik deploy** repo.

## Yapı

- `addon/SelamT/XFRMSeoBoost/` — XF eklenti kaynak kodu
- `translations/` — Türkçe phrase XML'leri (XF, XFRM, XFMG, SelamT_XFRMSeoBoost)
- `.cpanel.yml` — cPanel Git auto-deploy hook (push sonrası otomatik install/upgrade + phrase import)

## Workflow

1. Local'de `XenForo/src/addons/SelamT/XFRMSeoBoost/` klasöründe geliştirme
2. `_translations/` altındaki XML'leri güncelle (gerekirse export ile)
3. Bu repo'ya senkronize et (sync script — her şeyi `addon/` ve `translations/`'a kopyalar)
4. `git push origin main`
5. cPanel > Git Version Control > **Pull or Deploy** > **Update from Remote** + **Deploy HEAD Commit**
6. `.cpanel.yml` çalışır:
   - Eklenti dosyalarını `/home/forumino/public_html/src/addons/SelamT/` altına kopyalar
   - `_translations/` günceller
   - `xf-addon:install` veya `xf-addon:upgrade` çalıştırır
   - `selamt:import-translations` ile Türkçe çevirileri toplu import eder
   - Master data rebuild

## Sunucu Ayarları (ilk kurulum)

cPanel > Git Version Control > **Create**:
- Clone URL: GitHub repo HTTPS URL
- Repository Path: `/home/forumino/repos/forumino-deploy/`
- Repository Name: `forumino-deploy`
