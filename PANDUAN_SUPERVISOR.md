# Buku Panduan Penggunaan Supervisor

## Sistem Monitoring Keamanan

Panduan ini menjelaskan penggunaan akun **Supervisor** untuk mengatur jadwal patroli, memantau petugas, serta meninjau laporan patroli dan skip scan. Pengelolaan akun pengguna, lokasi, titik patroli, dan rute dilakukan melalui akun **Admin**.

## 1. Masuk ke Sistem

1. Buka alamat aplikasi yang diberikan oleh pengelola sistem.
2. Masukkan **NIPKWT** dan **Password** pada halaman masuk.
3. Pilih **Masuk ke Sistem**. Akun dengan role Supervisor akan diarahkan ke Dashboard Supervisor.
4. Jika gagal masuk, pastikan NIPKWT dan password benar serta akun aktif. Hubungi pengelola sistem untuk bantuan kredensial; tidak ada akun/password bawaan yang ditetapkan di panduan ini.

> Gunakan akun pribadi dan jangan membagikan password, terutama saat memakai komputer bersama.

## 2. Navigasi Supervisor

Sidebar menyediakan empat menu:

- **Dashboard**: ringkasan patroli, petugas, aktivitas, dan laporan.
- **Kelola Jadwal**: membuat, mengubah, menghapus, mencari, dan mengimpor jadwal.
- **Monitoring**: melihat status patroli dan petugas aktif pada peta.
- **Laporan**: mencari laporan/aktivitas, meninjau detail, dan mencetak rekap.

## 3. Dashboard Supervisor

Dashboard merangkum statistik patroli hari ini, progress, aktivitas terbaru, status petugas, serta jumlah laporan yang belum ditinjau. Data dashboard diperbarui otomatis setiap lima detik.

- Gunakan filter shift untuk mempersempit daftar petugas.
- Pilih **Lihat Semua** pada bagian monitoring untuk membuka halaman Monitoring.
- Pilih **Lihat Laporan** untuk membuka daftar laporan.
- Jika data gagal dimuat, pilih **Coba Lagi**.

## 4. Mengelola Jadwal

Buka **Kelola Jadwal**. Gunakan kolom pencarian nama/area dan rentang tanggal untuk mencari jadwal. Daftar ditampilkan 10 jadwal per halaman.

### Membuat jadwal

1. Pilih **Tambah jadwal**.
2. Pilih petugas Satpam. Petugas KATIM dapat dipilih sebagai pendamping secara opsional.
3. Pilih **Rute Patroli**. Rute beserta titik-titiknya harus sudah dibuat oleh Admin. Jadwal baru menggunakan rute; seluruh titik di rute mengikuti rentang tanggal dan jam shift yang sama.
4. Isi tanggal mulai, tanggal selesai, jam shift mulai, dan jam shift selesai. Semua kolom wajib.
5. Periksa kembali petugas, rute, tanggal, dan jam, lalu pilih **Simpan**.

Jika KATIM ditambahkan, empat putaran patroli dibagi bergantian 2:2: putaran 1 dan 3 untuk Satpam, putaran 2 dan 4 untuk KATIM. Jika tidak ada KATIM, seluruh putaran dilakukan Satpam.

### Mengubah atau menghapus jadwal

- Pilih ikon edit pada jadwal untuk mengubah petugas, area/titik, tanggal, jam shift, atau status. Pilih **Simpan** setelah perubahan.
- Pilih ikon hapus untuk menghapus jadwal. Penghapusan tidak dapat dikembalikan; periksa jadwal yang dipilih sebelum mengonfirmasi.

### Mengimpor jadwal dari Excel/CSV

1. Pilih **Import jadwal**.
2. Pilih **Download Template Excel** dan isi template. Setiap baris mewakili satu petugas yang dicocokkan menggunakan NIPKWT.
3. Isi kolom tanggal dengan kode shift: **P** untuk Pagi, **S** untuk Siang, **M** untuk Malam, dan **L** atau kosong untuk Libur. Rute yang ditentukan berlaku untuk bulan tersebut.
4. Pada form impor, pilih bulan dan tahun jadwal.
5. Pilih file `.xlsx`, `.xls`, atau `.csv`, lalu tekan **Upload & Proses**.
6. Periksa hasil impor, termasuk jumlah jadwal berhasil dan pesan kesalahan per baris. Koreksi file bila ada baris bermasalah lalu impor kembali.

## 5. Monitoring Patroli

Buka **Monitoring** untuk melihat ringkasan status patroli, peta titik keamanan, dan daftar Satpam aktif.

- Kartu statistik menunjukkan jumlah patroli berstatus Berjalan, Selesai, Terlambat, Anomali, Skip Scan, Terlewat, dan Offline.
- Pada peta, penanda hijau menunjukkan titik aktif dan abu-abu menunjukkan titik nonaktif. Pilih penanda untuk melihat nama/lokasi titik.
- Daftar **Satpam Aktif** menunjukkan petugas yang sedang bertugas beserta titik atau shift saat ini jika tersedia.
- Data diperbarui otomatis setiap 30 detik. Pilih **Refresh** untuk mengambil data terbaru secara manual.

Jika peta tidak tampil, periksa koneksi internet. Bila data monitoring gagal dimuat, pilih Refresh dan pastikan sesi login masih aktif.

## 6. Meninjau Laporan dan Skip Scan

Buka **Laporan** untuk mencari aktivitas patroli berdasarkan tanggal, status, atau nama Satpam. Tekan **Cari Laporan** setelah mengisi filter; tombol **Reset Filter** mengembalikan filter ke kondisi awal. Gunakan navigasi halaman untuk melihat hasil lainnya.

Status yang dapat muncul meliputi **Berhasil**, **Terlambat**, **Skip**, **Anomali**, dan **Terlewat**. Baris daftar menampilkan petugas, titik patroli, waktu, status, ringkasan laporan/alasan skip, dan status peninjauan.

### Membuka dan meninjau detail

1. Pilih **Lihat Detail** pada baris yang ingin diperiksa.
2. Periksa identitas petugas, titik, waktu, status, catatan, foto bukti (jika ada), serta isi laporan atau alasan skip.
3. Jika laporan belum ditinjau, pilih **Tandai Sudah Ditinjau** pada bagian Status Review.
4. Untuk aktivitas Skip Scan, periksa alasannya lalu gunakan tombol review pada bagian Skip Scan.
5. Pastikan status berubah menjadi **Sudah Ditinjau**, kemudian kembali ke daftar laporan.

Penandaan review mencatat bahwa Supervisor telah memeriksa laporan/skip; tindakan ini bukan mengubah hasil scan atau isi laporan.

### Mencetak rekap bulanan

1. Pada bagian **Recap Performa Patroli**, tentukan bulan dan tahun.
2. Periksa ringkasan jumlah target jadwal dan status scan.
3. Pilih **Cetak Recap PDF**. Gunakan dialog cetak browser untuk menyimpan sebagai PDF atau mencetak.
4. Jika dialog tidak muncul, izinkan pop-up untuk situs aplikasi.

## 7. Keluar dari Akun

Pilih **Keluar** di bagian bawah sidebar setelah pekerjaan selesai, khususnya pada perangkat bersama.

## 8. Pemecahan Masalah Singkat

| Kendala | Yang dapat dilakukan |
| --- | --- |
| Tidak dapat membuka menu Supervisor | Pastikan akun yang digunakan memiliki role Supervisor dan sesi login masih aktif. |
| Rute tidak tersedia saat membuat jadwal | Minta Admin membuat/mengaktifkan rute patroli terlebih dahulu. |
| Jadwal tidak dapat disimpan | Pastikan petugas, rute/area, tanggal mulai-selesai, dan jam shift telah diisi; periksa pesan validasi. |
| Impor memiliki baris bermasalah | Cocokkan NIPKWT dan kode shift dengan template; baca nomor baris dan pesan kesalahan hasil impor. |
| Monitoring atau peta tidak tampil | Periksa koneksi internet, pilih Refresh, lalu coba lagi. |
| Detail laporan atau bukti gagal dimuat | Pilih **Coba Lagi** jika tersedia dan pastikan koneksi serta sesi login aktif. |
| PDF tidak terbuka | Izinkan pop-up browser, lalu pilih Cetak Recap PDF kembali. |

Jika kendala tetap terjadi, catat halaman, langkah terakhir, dan pesan kesalahan, lalu sampaikan kepada pengelola sistem.