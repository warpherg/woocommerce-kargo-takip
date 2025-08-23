# WooCommerce Kargo Takip (TR)

Türkiye içi kargo takip eklentisi. Siparişe “Kargoda” durumu ekler, kargo firması ve takip numarası alanları sağlar, müşteri e-postaları gönderir, teslim-webhook/cron ile otomatik tamamlar ve müşteri tarafında takip/rozet gösterir.

## Başlıca Özellikler
- "Kargoda" özel sipariş durumu (HPOS uyumlu)
- Yönetici sipariş ekranında: kargo firması, takip numarası, “Teslim edildi” + teslim tarihi
- Müşteri e-postaları: “Kargoda” ve “Teslim edildi” (özelleştirilebilir şablonlar)
- Müşteri sipariş detayında takip kartı, kopyala butonu ve teslim rozeti
- Kargo firması bazlı URL şablonları (ayarlar üzerinden), değişken: `{tracking}`
- Webhook: teslim bildirimi (order_id veya tracking ile)
- Kargo hareketleri import API’si (timeline olarak saklanır ve gösterilir)
- Sipariş listesi kolonu ve toplu işlemler (Kargoda yap / Teslim edildi ve tamamla)
- Otomatik teslim kontrolü (cron) — anahtar kelime (regex) ile tespit + basit cache/backoff
- Otomatik taşıyıcı tespiti — regex desenleriyle carrier seçimi
- Public takip formu shortcode’u
- CSV içe/dışa aktarım sayfası (WooCommerce > Kargo CSV)
- Dashboard KPI widget’ı (son 7 gün Kargoda/Teslim edildi)
- REST güvenliği: IP beyaz liste ve saatlik oran sınırlaması
- Opsiyonel Twilio SMS bildirimleri (Kargoda/Teslim edildi)
- Minimal WP‑CLI komutu (takip set etme)
- Tam/parsiyel iade sonrası otomatik “İade Edildi” durum güncellemesi ve kargo metalarının temizlenmesi

## Kurulum
1. Klasörü `wp-content/plugins/woocommerce-kargo-takip` yoluna kopyalayın.
2. WP Yönetim > Eklentiler’den “WooCommerce Kargo Takip (TR)” eklentisini etkinleştirin.
3. WooCommerce etkin olmalıdır.

## Ayarlar
Yol: WooCommerce > Ayarlar > Kargo > Kargo Takip
- Webhook Secret: REST çağrıları için gizli anahtar.
- Kargo Link Şablonları: Her firma için `{tracking}` içeren link.
- Taşıyıcı Desenleri: Regex ile otomatik taşıyıcı tespiti (örn: `^[A-Z]{2}\d{8}$`).
- Otomatik Teslim Kontrolü (cron): Etkin/Devre dışı ve anahtar kelimeler (regex).
- REST Güvenlik: İzinli IP listesi (virgülle), saatlik istek limiti.
- SMS (Twilio): SID/Token/From ayarları ve etkinleştirme.

## Sipariş Üzerinde
- “Kargo Takip” bölümünden firma ve takip numarası girin.
- “Ek Gönderiler” alanında her satıra `carrier|tracking` formatında çoklu gönderi ekleyin.
- Durumu “Kargoda” yaparsanız müşteri “Kargoda” e-postası/SMS (varsa) alır.
- “Teslim edildi” işaretlenirse sipariş otomatik “Tamamlandı” olur, e‑posta/SMS gider.
- Stripe vb. ödeme iadesi tamlandığında sipariş otomatik “İade Edildi” olur, kargo metaları temizlenir.

## Kısa Kodlar
- Takip bloğu: `[wc_kargo_takip order_id="72"]`
- Public takip formu: `[wc_kargo_form]` veya yönlendirmeli `[wc_kargo_form redirect="/takip-sonucu"]`

## REST API
- Teslim edildi: `POST /wp-json/wc-kargo-takip/v1/delivered`
  - Parametreler: `secret`, `order_id` veya `tracking`
- Kargo hareketi import: `POST /wp-json/wc-kargo-takip/v1/events`
  - Parametreler: `secret`, `order_id`, `events` (array: `{ time, description }`)

Örnek:
```bash
curl -X POST https://alanadiniz.com/wp-json/wc-kargo-takip/v1/delivered \
  -d secret=WEBHOOK_SECRET \
  -d order_id=72
```

```bash
curl -X POST https://alanadiniz.com/wp-json/wc-kargo-takip/v1/events \
  -H "Content-Type: application/json" \
  -d '{
    "secret":"WEBHOOK_SECRET",
    "order_id":72,
    "events":[{"time":"2025-08-23 10:35","description":"Şubeye ulaştı"}]
  }'
```

## CSV İçe/Dışa Aktarım
Yol: WooCommerce > Kargo CSV
- İçe aktarma CSV kolonları: `order_id,carrier,tracking,delivered(0/1),delivered_date(Y-m-d H:i:s)`
- Dışa aktarma: sayfadaki “CSV İndir” butonu

## Dashboard Widget
- “Kargo Takip Göstergeleri”: Son 7 gün “Kargoda” ve “Teslim edildi” sipariş sayıları

## WP‑CLI
- Takip set et: `wp wc-kargo set-tracking <order_id> <carrier> <tracking>`
  - `carrier` boş bırakılırsa regex desenlerinden otomatik tespit denenir

## Desteklenen Kargo Firmaları (varsayılanlar)
- Yurtiçi Kargo
- Aras Kargo
- MNG Kargo
- Sürat Kargo
- PTT Kargo
- Hepsijet
- Trendyol Express

Liste ve varsayılan link mantığı `includes/class-wc-kargo-takip.php` içindeki `get_supported_carriers()` ve `build_tracking_url()` ile yönetilir. Ayarlardaki URL şablonu boş değilse o şablon kullanılır.

## Geliştirme
- Kod: `woocommerce-kargo-takip.php`, `includes/class-wc-kargo-takip.php`
- E-postalar: `includes/emails/`
- Şablonlar: `templates/`
- CSS: `assets/`

## Değişiklik Günlüğü
- 1.1.0
  - Ayarlar alt menüsü, kargo link şablonları, cron teslim kontrolü
  - “Kargoda” ve “Teslim edildi” e-posta şablonları
  - Müşteri tarafında takip kartı ve teslim rozeti
  - Webhook uçları: delivered, events
  - Admin sipariş listesi kolonu ve toplu işlemler
  - HPOS uyumluluğu
- 1.2.0
  - Timeline (events) depolama ve gösterim
  - Otomatik taşıyıcı tespiti (regex)
  - Public takip formu shortcode’u
  - CSV içe/dışa aktarma sayfası
  - Dashboard KPI widget’ı
  - REST IP beyaz liste + rate limit
  - Cron cache/backoff
  - Twilio SMS entegrasyonu (opsiyonel)
  - WP‑CLI komutu
  - İade akışı ile otomatik “İade Edildi” ve meta temizliği

## Lisans
GNU GPL v2 veya sonrası.


