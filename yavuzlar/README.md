# Yavuzlar – Docker ile Çalıştırma

Bu repo, PHP (Apache) + SQLite kullanan bir web uygulamasıdır. Docker ile hızlıca ayağa kaldırabilir, verileri kalıcı bir volume içinde saklayabilirsiniz.

## Gereksinimler
- Docker Desktop (veya Docker Engine)
- Docker Compose v2

## Hızlı Başlangıç
1) Container'ı başlatın:

```
docker compose up -d --build
```

2) Veritabanını bir kez initialize edin:

```
docker compose exec app php init_db.php
```

Not: `init_db.php` betiği çalıştığında tabloları düşürüp yeniden oluşturur. Bu nedenle sadece ilk kurulumda veya verileri sıfırlamak istediğinizde çalıştırın.

3) Uygulamaya tarayıcıdan erişin:
- http://localhost:8080

## Varsayılan Hesaplar
- Admin: `admin@yavuzlar.com` / `Admin123!`
- Firma Yetkilisi: `firma@yavuzlar.com` / `Firma123!`
- Kullanıcı: `user@yavuzlar.com` / `User123!`

## Mimari
- Image: `php:8.2-apache`
- Veritabanı: SQLite (dosya: `database/app.sqlite`)
- Kalıcılık: `docker-compose.yml` içinde `db_data` isimli volume, container içinde `/var/www/html/database` yoluna bağlanır.

## Faydalı Komutlar
- Logları izlemek: `docker compose logs -f`
- Uygulamayı durdurmak: `docker compose down`
- Veritabanı dahil her şeyi sıfırlamak: `docker compose down -v`
- Container içine girip PHP çalıştırmak: `docker compose exec app bash` (Debian shell varsa) veya `docker compose exec app php -v`

## Geliştirme Notları
- Uygulama kodu image içine kopyalanır. Canlı düzenleme yapmak yerine image'ı yeniden build etmeniz gerekir (`docker compose up -d --build`).
- Veritabanı klasörü (`/var/www/html/database`) yazılabilir olacak şekilde ayarlanmıştır ve named volume ile kalıcıdır.

## Sorun Giderme
- Sayfa açılmıyorsa: `docker compose logs -f app` ile hataları kontrol edin.
- Veritabanı hatası alıyorsanız: volume'ın bağlı olduğundan emin olun ve gerekirse `init_db.php`'yi yeniden çalıştırmadan önce verilerinizi yedekleyin.

