# WooCommerce Kargo Takip (TR)

Türkiye içi kargo takip eklentisi. Siparişe “Kargoda” durumu ekler, kargo firması ve takip numarası alanları sağlar, müşteri e-postaları gönderir, teslim-webhook/cron ile otomatik tamamlar ve müşteri tarafında takip/rozet gösterir.

## Başlıca Özellikler
- "Kargoda" özel sipariş durumu (HPOS uyumlu)
- Yönetici sipariş ekranında: kargo firması, takip numarası, “Teslim edildi” + teslim tarihi
- Müşteri e-postaları: “Kargoda” ve “Teslim edildi” (özelleştirilebilir şablonlar)
- Müşteri sipariş detayında takip linki ve “Teslim edildi” rozeti
- Kargo firması bazlı URL şablonları (ayarlar üzerinden), değişken: `{tracking}`
- Webhook: teslim bildirimi (order_id veya tracking ile)
- Kargo hareketleri import API’si: sipariş notu olarak ekler
- Sipariş listesi kolonu ve iki toplu işlem (Kargoda yap / Teslim edildi ve tamamla)
- Opsiyonel saatlik otomatik teslim kontrolü (cron) — anahtar kelime ile tespit

## Kurulum
1. Klasörü `wp-content/plugins/woocommerce-kargo-takip` yoluna kopyalayın.
2. WP Yönetim > Eklentiler’den “WooCommerce Kargo Takip (TR)” eklentisini etkinleştirin.
3. WooCommerce etkin olmalıdır.

## Ayarlar ve Kullanım
- Yol: WooCommerce > Ayarlar > Kargo > Kargo Takip
- Webhook Secret: Webhook çağrıları için gizli anahtarınızı girin.
- Kargo Link Şablonları: Her firma için URL şablonu tanımlayın. Ör: `https://kargotakip.araskargo.com.tr/mainpage.aspx?code={tracking}`
- Otomatik Teslim Kontrolü (cron): Etkinleştirirseniz saatlik kontrol yapar. Anahtar kelimeleri (regex) düzenleyebilirsiniz (örn. `TESLIM|TESLİM|DELIVERED|TESLİM EDİLDİ`).

### Sipariş Üzerinde
1. Sipariş detayında “Kargo Takip” başlığından firma ve takip numarası girin.
2. Durumu “Kargoda” yaparsanız müşteri “Kargoda” e-postası alır.
3. “Teslim edildi” işaretleyip kaydederseniz sipariş otomatik “Tamamlandı” olur ve müşteri “Teslim edildi” e-postası alır.

### Kısa Kod
`[wc_kargo_takip order_id="72"]` — takip bloğunu herhangi bir sayfaya yerleştirir.

### Webhook’lar
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

## Desteklenen Kargo Firmaları (varsayılanlar)
- Yurtiçi Kargo
- Aras Kargo
- MNG Kargo
- Sürat Kargo
- PTT Kargo
- Hepsijet
- Trendyol Express

Liste ve linkler `includes/class-wc-kargo-takip.php` içindeki `get_supported_carriers()` ve `build_tracking_url()` ile yönetilir. Ayarlardaki URL şablonu boş değilse o şablon kullanılır.

## Teknik Notlar
- Metalar: `_wc_kargo_takip_carrier`, `_wc_kargo_takip_number`, `_wc_kargo_teslim_edildi`, `_wc_kargo_teslim_tarihi`
- E-posta sınıfları: `includes/emails/` altında
- Şablonlar: `templates/emails/*`

## Geliştirme ve Katkı
Eklenti sahibi: warpherg — sürüm: 1.1.0


