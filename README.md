# IRNIS AI CORE

Asisten AI percakapan (agent) berbasis **Laravel + PostgreSQL (pgvector) + Ollama (Qwen)** dengan RAG (retrieval-augmented generation) dan tool-calling. Dilengkapi dashboard web (Blade), REST API, audit trail, dan basis pengetahuan yang diindeks ke vektor.

## Daftar Isi

- [Fitur](#fitur)
- [Software yang Digunakan](#software-yang-digunakan)
- [Instalasi](#instalasi)
  - [1. Persiapan PostgreSQL + pgvector](#1-persiapan-postgresql--pgvector)
  - [2. Instal Ollama + Model](#2-instal-ollama--model)
  - [3. Siapkan PHP + Composer](#3-siapkan-php--composer)
  - [4. Pasang Aplikasi](#4-pasang-aplikasi)
  - [5. Konfigurasi .env](#5-konfigurasi-env)
  - [6. Migrasi & Seed](#6-migrasi--seed)
  - [7. Jalankan Server](#7-jalankan-server)
- [Akun Demo](#akun-demo-seeder)
- [Cara Pakai Chat](#cara-pakai-chat)
- [REST API](#rest-api)
- [Perintah CLI](#perintah-cli)
- [Testing](#testing)
- [Struktur Proyek](#struktur-proyek)

---

## Fitur

- **Agent loop dengan tool-calling** (`qwen2.5:1.5b`) + fallback ekstraksi tool-call dari teks untuk model kecil.
- **RAG**: dokumen dipecah → chunk → embedding (`nomic-embed-text`) → pencarian kosinus di **pgvector**.
- **Upload dokumen**: PDF (smalot/pdfparser), DOCX (ZipArchive), TXT/MD — via API maupun dashboard.
- **Tool berbasis permission/role**: ringkasan penjualan, produk, pelanggan, pencarian basis pengetahuan.
- **Observability & audit**: `agent_run.started/finished`, `tool_call.denied`, `knowledge.document_uploaded/indexed`; transcript lengkap per run.
- **Dashboard web**: Chat, Conversations, Agent Runs, Tools, Knowledge — data per-user; admin melihat semua dan dapat upload.

---

## Software yang Digunakan

| Software | Versi yang dipakai | Peran di proyek ini |
|---|---|---|
| **PHP** | ^8.3 (8.3.28) | Runtime aplikasi. Laravel berjalan di atasnya; butuh ekstensi `dom`, `mbstring`, `xml`, `zip`. |
| **Composer** | 2.x | Manajer dependensi PHP. Mengunduh Laravel + pustaka lain (`smalot/pdfparser`, `laravel/sanctum`, dst). |
| **Laravel framework** | ^13.17 | Framework web utama: routing, ORM (Eloquent), bladenya, auth, migrasi, testing. |
| **PostgreSQL** | 16+ (18) | Database utama semua data aplikasi: pengguna, percakapan, pesan, agent runs, tool calls, dokumen knowledge, audit log. |
| **pgvector** | 0.8.6 (ekstensi PostgreSQL) | Penyimpanan & pencarian *embedding vector* — inti RAG. Kolom `embedding vector(768)` di tabel `document_chunks`. |
| **Ollama** | ≥ 0.3 | Menjalankan model LLM lokal. Tanpa mengirim data ke cloud. |
| **qwen2.5:1.5b** | model chat | Dijalankan Ollama; bertindak sebagai "otak" agent: memahami prompt, memutuskan tool yang dipanggil, menyusun jawaban. |
| **nomic-embed-text** | model embedding | Mengubah teks (chunk dokumen & query) menjadi vektor 768 dimensi untuk pencarian semantik. |
| **smalot/pdfparser** | 2.11 | Pustaka PHP untuk mengekstrak teks dari PDF saat upload dokumen. |
| **laravel/sanctum** | ^4.0 | Autentikasi API berbasis token (header `Authorization: Bearer`). |
| **Web server** | `php artisan serve` / Laragon | Melayani HTTP aplikasi. |

### Diagram alur kerja

```
User → Dashboard/API → AgentRunner
                          │
                          ├─ Ollama (qwen2.5) → jawaban/pilih tool
                          ├─ ToolRegistry → sales/product/customer/knowledge
                          ├─ KnowledgeService → nomic-embed-text → pgvector query
                          └─ AuditService → audit_logs
```

---

## Instalasi

> Contoh perintah berikut untuk **Windows (Laragon)**, disertai catatan macOS/Linux di tiap langkah.

### 1. Persiapan PostgreSQL + pgvector

**Windows (via Laragon)**

1. Instal **Laragon** → menu **Menu > PostgreSQL > Install**. Laragon menyediakan PostgreSQL lengkap dengan PHP.
2. Aktifkan PostgreSQL: **Menu > PostgreSQL > Start**.
3. Buka `psql` (atau gunakan pgAdmin):
   ```sql
   CREATE DATABASE irnis_ai;
   CREATE DATABASE irnis_ai_test;
   ```
4. **pgvector** tidak tersedia otomatis dari installer PostgreSQL Windows. Unduh rilis pgvector yang sesuai versi PostgreSQL Anda, lalu:
   - salin `vector.dll` → `C:\Program Files\PostgreSQL\<versi>\lib`
   - salin `vector.control` dan `vector--*.sql` → `...\share\extension`
   - (jalankan `psql` sebagai admin bila perlu)
   - aktivasi ekstensi di tiap database:
     ```sql
     \c irnis_ai
     CREATE EXTENSION IF NOT EXISTS vector;
     \c irnis_ai_test
     CREATE EXTENSION IF NOT EXISTS vector;
     ```
5. Verifikasi:
   ```sql
   SELECT extversion FROM pg_extension WHERE extname='vector';
   ```
   → contoh hasil `0.8.6`.

**macOS**

```sh
brew install postgresql@16  # atau versi lain
brew install pgvector
psql -U postgres -c "CREATE DATABASE irnis_ai;"
psql -U postgres -c "CREATE DATABASE irnis_ai_test;"
psql -U postgres -d irnis_ai -c "CREATE EXTENSION vector;"
psql -U postgres -d irnis_ai_test -c "CREATE EXTENSION vector;"
```

**Debian/Ubuntu**

```sh
sudo apt update
sudo apt install postgresql postgresql-16-pgvector
sudo -u postgres psql -c "CREATE DATABASE irnis_ai;"
sudo -u postgres psql -c "CREATE DATABASE irnis_ai_test;"
sudo -u postgres psql -d irnis_ai -c "CREATE EXTENSION vector;"
sudo -u postgres psql -d irnis_ai_test -c "CREATE EXTENSION vector;"
```

### 2. Instal Ollama + Model

1. Unduh & instal [Ollama](https://ollama.com) sesuai OS (Windows/macOS/Linux) lalu jalankan aplikasinya (ikon llama di tray menandakan server aktif).
2. Tarik model yang dibutuhkan:
   ```sh
   ollama pull qwen2.5:1.5b
   ollama pull nomic-embed-text
   ```
3. Pastikan server dapat diakses:
   ```sh
   curl http://127.0.0.1:11434
   ```
   → respon `Ollama is running`.

> `qwen2.5:1.5b` adalah model kecil agar berjalan di laptop. Anda dapat mengganti model (mis. `qwen2.5:7b`) di `.env` (`OLLAMA_MODEL`), namun sesuaikan dimensi embedding bila model embedding diganti (`OLLAMA_EMBEDDING_DIMENSIONS`).

### 3. Siapkan PHP + Composer

- **Laragon** sudah menyertakan PHP. Cek versi:
  ```sh
  php -v        # butuh ^8.3
  composer -V   # butuh 2.x
  ```
- Jika `php`/`composer` tidak ada di PATH, gunakan jalur lengkap, contoh:
  ```powershell
  C:\laragon\bin\php\8.3.28\php.exe -v
  C:\laragon\bin\composer\composer.bat -V
  ```
- macOS: `brew install php composer` · Linux: `sudo apt install php-cli php-zip php-mbstring php-xml php-dom` lalu `curl -sS https://getcomposer.org/installer | php`.

### 4. Pasang Aplikasi

```sh
git clone <repo-url> irnis-ai
cd irnis-ai
composer install
```

Lakukan dari folder proyek Laragon agar langsung tervirtualisasi host, mis. `C:\laragon\www\irnis-ai`, lalu akses via `http://irnis-ai.test` (Laragon) atau jalankan `php artisan serve`.

### 5. Konfigurasi .env

```sh
cp .env.example .env
php artisan key:generate
```

Sesuaikan kredensial database dan Ollama pada `.env`:

```env
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=irnis_ai
DB_USERNAME=postgres
DB_PASSWORD=<password postgres Anda>

OLLAMA_URL=http://127.0.0.1:11434
OLLAMA_MODEL=qwen2.5:1.5b
OLLAMA_EMBEDDING_MODEL=nomic-embed-text
OLLAMA_EMBEDDING_DIMENSIONS=768
# opsional:
# OLLAMA_TIMEOUT=120
# OLLAMA_TEMPERATURE=0.2
# OLLAMA_TOP_K=3
```

### 6. Migrasi & Seed

```sh
php artisan migrate --seed
```

Migrasi membuat seluruh skema: `users`, `agents`, `conversations`, `messages`, `agent_runs`, `tool_calls`, `sales_orders`, `knowledge_sources`, `documents`, `document_chunks` (kolom `embedding vector(768)`), `audit_logs`, `personal_access_tokens`.

Seed (idempoten, aman diulang) membuat:
- Akun demo (lihat [Akun Demo](#akun-demo-seeder)).
- Data penjualan sampel (`sales_orders`) untuk tool penjualan.
- 3 percakapan + agent runs + tool calls (sukses & ditolak karena permission).
- 2 dokumen SOP yang **diindeks langsung ke vektor via Ollama**; jika Ollama tidak berjalan, bagian ini dilewati dengan peringatan (sisa data tetap dibuat).

### 7. Jalankan Server

```sh
php artisan serve
# buka http://localhost:8000
```

> Menggunakan Laragon? Letakkan proyek di `C:\laragon\www\irnis-ai` → otomatis terbuka di `http://irnis-ai.test`.

---

## Akun Demo (Seeder)

| Role | Email | Password | Kemampuan |
|---|---|---|---|
| Admin | `admin@irnis.test` | `password` | Semua data, upload dokumen ke basis pengetahuan |
| User | `test@example.com` | `password` | Dashboard & chat (hanya data milik sendiri), tidak bisa upload |

Login: `http://localhost:8000/login`.

---

## Cara Pakai Chat

Topbar dashboard → menu **Chat** (`/dashboard/chat`):

- Pilih percakapan dari daftar kiri, atau **+ Baru** untuk memulai percakapan.
- Coba pertanyaan yang memicu tool:
  - *"Berapa total penjualan bulan September?"* → memanggil tool `get_sales_summary`.
  - *"Berapa hari batas retur?"* → memanggil `search_knowledge` (RAG) dan menjawab dari SOP.
- Hasil tool call ditampilkan sebagai langkah (step) di bawah jawaban.

---

## REST API

Semua endpoint API mengirim token Sanctum di header:

```sh
php artisan tinker
$user = App\Models\User::where('email','admin@irnis.test')->first();
$user->createToken('dev')->plainTextToken;
```

| Method | Endpoint | Deskripsi |
|---|---|---|
| `POST` | `/api/chat` | Mengirim pesan ke agent. Body: `message` (wajib), `conversation_id`/`agent_id` (opsional). |
| `GET` | `/api/conversations` | Daftar percakapan milik user (paginated). |
| `GET` | `/api/conversations/{id}` | Transcript: pesan + agent runs + tool calls. |
| `POST` | `/api/knowledge/documents` | Upload dokumen (multipart `file`; txt/md/pdf/docx, ≤5 MB). Hanya admin. |
| `GET` | `/api/user` | Data user yang sedang login. |

Contoh:

```sh
# Chat (jawaban dari agent, RAG/tool aktif)
curl -X POST http://localhost:8000/api/chat \
  -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"message":"Berapa hari batas retur?"}'

# Transcript percakapan
curl http://localhost:8000/api/conversations/1 -H "Authorization: Bearer <TOKEN>"

# Upload dokumen ke basis pengetahuan
curl -X POST http://localhost:8000/api/knowledge/documents \
  -H "Authorization: Bearer <TOKEN>" \
  -F "file=@sop-cuti.docx" -F "title=SOP Cuti" -F "source=Upload Manual"
```

---

## Perintah CLI

```sh
php artisan knowledge:index path/ke/sop.pdf --source="SOP Internal"   # indeks PDF/DOCX/TXT
php artisan db:seed                             # seed (idempoten)
php artisan tinker                              # REPL; buat token API, dll.
php artisan migrate:fresh --seed                # reset DB + seed ulang
```

---

## Testing

Suite berjalan di database PostgreSQL `irnis_ai_test` (konfigurasi di `phpunit.xml`). Panggilan HTTP ke Ollama di-*mock*, kecuali test live:

```sh
php artisan test --compact

# Live end-to-end (butuh Ollama berjalan; chat + RAG asli)
# PowerShell:
$env:OLLAMA_LIVE_TEST="true"; php artisan test tests/Feature/LiveChatE2ETest.php
# bash:
OLLAMA_LIVE_TEST=true php artisan test tests/Feature/LiveChatE2ETest.php
```

---

## Struktur Proyek

```
app/
  Agents/AgentRunner.php          # loop agent: chat → tool-calling → audit
  Services/                       # OllamaService, EmbeddingService, KnowledgeService,
                                  # DocumentExtractor, TextChunker, AuditService
  Tools/                          # ToolInterface + Sales/Product/Customer/KnowledgeTool + registry
  Http/Controllers/               # API (Chat/Conversation/KnowledgeUpload) & Dashboard
  Console/Commands/KnowledgeIndex.php
resources/views/dashboard/**      # Blade dashboard (chat, conversations, runs, tools, knowledge)
database/seeders/                 # DatabaseSeeder, SalesOrderSeeder, SampleDataSeeder
routes/{web,api}.php
config/ollama.php                 # konfigurasi model/temperature/top_k
PRD_IRNIS_AI_CORE_Roadmap.md      # rencana produk
```

---

## Lisensi

Proyek internal/riset. Kode dasar Laravel berlisensi MIT.