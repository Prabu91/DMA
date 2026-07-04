CREATE TABLE "cabang" (
  "id" int PRIMARY KEY,
  "nama" varchar,
  "kode_area" varchar
);

CREATE TABLE "kota" (
  "id" int PRIMARY KEY,
  "nama" varchar,
  "cabang_id" int
);

CREATE TABLE "users" (
  "id" int PRIMARY KEY,
  "cabang_id" int,
  "nama" varchar,
  "email" varchar,
  "role" varchar,
  "kode_role" varchar,
  "no_telp" varchar
);

CREATE TABLE "sekolah" (
  "id" int PRIMARY KEY,
  "id_sekolah" varchar UNIQUE,
  "nama" varchar,
  "alamat" varchar,
  "kota" varchar,
  "pic_sekolah" varchar,
  "no_telp_pic" varchar,
  "email_guru" varchar,
  "maps_link" varchar,
  "cabang_id" int
);

CREATE TABLE "kategori" (
  "id" int PRIMARY KEY,
  "nama" varchar,
  "pakai_desain" boolean
);

CREATE TABLE "produk" (
  "id" int PRIMARY KEY,
  "kategori_id" int,
  "nama" varchar,
  "gaya" varchar,
  "deskripsi" varchar,
  "foto" varchar,
  "harga" int,
  "status" varchar
);

CREATE TABLE "produk_opsi" (
  "id" int PRIMARY KEY,
  "produk_id" int,
  "tipe_opsi" varchar,
  "nilai_opsi" varchar,
  "harga_override" int,
  "is_wajib" boolean
);

CREATE TABLE "desain" (
  "id" int PRIMARY KEY,
  "kategori_id" int,
  "kode" varchar,
  "seri" varchar,
  "orientasi" varchar,
  "foto_preview" varchar,
  "tahun_ajaran" varchar,
  "status" varchar
);

CREATE TABLE "paket" (
  "id" int PRIMARY KEY,
  "nama" varchar,
  "deskripsi" varchar,
  "harga" int,
  "status" varchar
);

CREATE TABLE "paket_produk" (
  "paket_id" int,
  "produk_id" int
);

CREATE TABLE "aturan_free_sekolah" (
  "id" int PRIMARY KEY,
  "paket_id" int,
  "basis" varchar,
  "operator" varchar,
  "nilai" int,
  "hasil_produk_id" int,
  "hasil_ukuran" varchar
);

CREATE TABLE "produk_bonus" (
  "id" int PRIMARY KEY,
  "produk_id" int,
  "bonus_produk_id" int,
  "qty" int
);

CREATE TABLE "orders" (
  "id" int PRIMARY KEY,
  "booking_code" varchar UNIQUE,
  "sekolah_id" int,
  "marketing_id" int,
  "cabang_id" int,
  "status" varchar,
  "event_status" varchar,
  "tanggal_event" date,
  "jam_event" varchar,
  "jumlah_siswa" int,
  "keterangan" varchar,
  "total" int,
  "otp_code" varchar,
  "otp_expires" datetime,
  "tahun_ajaran" varchar,
  "tanggal_booking" datetime
);

CREATE TABLE "order_items" (
  "id" int PRIMARY KEY,
  "order_id" int,
  "tipe_item" varchar,
  "produk_id" int,
  "paket_id" int,
  "desain_id" int,
  "opsi_ukuran" varchar,
  "qty" int,
  "harga" int,
  "is_free" boolean
);

CREATE TABLE "order_tim_event" (
  "order_id" int,
  "user_id" int
);

CREATE UNIQUE INDEX ON "users" ("cabang_id", "kode_role");

ALTER TABLE "kota" ADD FOREIGN KEY ("cabang_id") REFERENCES "cabang" ("id") DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "users" ADD FOREIGN KEY ("cabang_id") REFERENCES "cabang" ("id") DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "sekolah" ADD FOREIGN KEY ("cabang_id") REFERENCES "cabang" ("id") DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "produk" ADD FOREIGN KEY ("kategori_id") REFERENCES "kategori" ("id") DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "produk_opsi" ADD FOREIGN KEY ("produk_id") REFERENCES "produk" ("id") DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "desain" ADD FOREIGN KEY ("kategori_id") REFERENCES "kategori" ("id") DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "paket_produk" ADD FOREIGN KEY ("paket_id") REFERENCES "paket" ("id") DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "paket_produk" ADD FOREIGN KEY ("produk_id") REFERENCES "produk" ("id") DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "aturan_free_sekolah" ADD FOREIGN KEY ("paket_id") REFERENCES "paket" ("id") DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "aturan_free_sekolah" ADD FOREIGN KEY ("hasil_produk_id") REFERENCES "produk" ("id") DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "produk_bonus" ADD FOREIGN KEY ("produk_id") REFERENCES "produk" ("id") DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "produk_bonus" ADD FOREIGN KEY ("bonus_produk_id") REFERENCES "produk" ("id") DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "orders" ADD FOREIGN KEY ("sekolah_id") REFERENCES "sekolah" ("id") DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "orders" ADD FOREIGN KEY ("marketing_id") REFERENCES "users" ("id") DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "orders" ADD FOREIGN KEY ("cabang_id") REFERENCES "cabang" ("id") DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "order_items" ADD FOREIGN KEY ("order_id") REFERENCES "orders" ("id") DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "order_items" ADD FOREIGN KEY ("produk_id") REFERENCES "produk" ("id") DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "order_items" ADD FOREIGN KEY ("paket_id") REFERENCES "paket" ("id") DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "order_items" ADD FOREIGN KEY ("desain_id") REFERENCES "desain" ("id") DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "order_tim_event" ADD FOREIGN KEY ("order_id") REFERENCES "orders" ("id") DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "order_tim_event" ADD FOREIGN KEY ("user_id") REFERENCES "users" ("id") DEFERRABLE INITIALLY IMMEDIATE;
