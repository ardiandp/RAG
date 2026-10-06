# IRNIS AI CORE

Asisten AI percakapan (agent) berbasis **Laravel + PostgreSQL (pgvector) + Ollama (Qwen)** dengan RAG dan tool-calling. Termasuk dashboard web (Blade), REST API, audit trail, dan basis pengetahuan yang diindeks ke vektor.

## Fitur

- **Agent loop** dengan tool-calling (`qwen2.5`) + fallback ekstraksi tool-call dari teks.
- **RAG** (retrieval-augmented generation): dokumen dipecah → chunk → embedding (`nomic-embed-text`) → pencarian kosinus di **pgvector**.
- **Upload dokumen**: PDF, DOCX, TXT/MD (via API maupun dashboard).
- **Tool**: ringkasan penjualan, produk, pelanggan, pencarian basis pengetahuan — berbasis permission/role.
- **Observability**: audit log (`agent_run.started/finished`, `tool_call.denied`, `knowledge.document_uploaded/indexed`), transcript lengkap per run.
- **Dashboard web**: Chat, Conversations, Agent Runs, Tools, Knowledge — data per-user, admin dapat melihat semua & upload.

## Software yang Diperlukan

| Software | Versi | Catatan |
|---|---|---|
| PHP | ^8.3 | Wajib ekstensi `dom`, `mbstring`, `xml`, `zip` (default Laragon) |
| Composer | 2.x | — |
| PostgreSQL | 16+ (dipakai 18) | Wajib ekstensi **pgvector** |
| Ollama | ≥ 0.3 | Chat model + embedding model (lihat di bawah) |
| Web server | `php artisan serve` atau Laragon/Vite dev | — |
| (Windows) Laragon | 6.x | Cara mudah menyediakan PHP + PostgreSQL |

### Model Ollama

```sh
ollama pull qwen2.5:1.5b
ollama pull nomic-embed-text
```

- `qwen2.5:1.5b` → model chat/tool-calling.
- `nomic-embed-text` → model embedding (768 dimensi).

Pastikan server Ollama berjalan dan dapat diakses: `http://127.0.0.1:11434`.

### pgvector (PostgreSQL)

Aktifkan ekstensi di setiap database (dev & test):

```sql
CREATE EXTENSION IF NOT EXISTS vector;
```

- **macOS**: `brew install pgvector`
- **Debian/Ubuntu**: `apt install postgresql-16-pgvector` (jika versi PostgreSQL 14-17), lalu `CREATE EXTENSION vector` di database.
- **Windows (manual)**:
  1. Unduh rilis pgvector untuk versi PostgreSQL Anda, salin `vector.dll` ke `C:\Program Files\PostgreSQL\<versi>\lib` dan `vector.control` + `vector--*.sql` ke `...\share\extension`.
  2. Jalankan `CREATE EXTENSION IF NOT EXISTS vector;` di setiap database.

Verifikasi: `SELECT extversion FROM pg_extension WHERE extname='vector';` → contoh `0.8.6`.

## Instalasi

```sh
# 1. Clone & install dependensi
git clone <repo-url> irnis-ai
cd irnis-ai
composer install

# 2. Konfigurasi
cp .env.example .env
php artisan key:generate
```

Isi `.env`:

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
# OLLAMA_TIMEOUT=120, OLLAMA_TEMPERATURE=0.2, OLLAMA_TOP_K=3
```

Untuk menjalankan tes juga perlu database terpisah:

```sh
psql -U postgres -c "CREATE DATABASE irnis_ai;"
psql -U postgres -c "CREATE DATABASE irnis_ai_test;"
```

Lalu migrasi + seed:

```sh
php artisan migrate --seed
```

Migrasi membuat skema (users, agents, conversations, messages, agent_runs, tool_calls, sales_orders, knowledge_sources, documents, document_chunks dengan kolom `embedding vector(768)`, audit_logs, personal_access_tokens). Seed membuat akun demo + data sampel (3 percakapan, agent runs, tool calls, dan 2 dokumen SOP terindeks via Ollama — bagian knowledge otomatis dilewati bila Ollama mati).

Jalankan server:

```sh
php artisan serve
# buka http://localhost:8000
```

## Akun Demo (Seeder)

| Role | Email | Password |
|---|---|---|
| Admin (full akses + upload) | `admin@irnis.test` | `password` |
| User (dashboard, chat; data milik sendiri) | `test@example.com` | `password` |

Login: `http://localhost:8000/login` → dashboard. Menu **Chat** di topbar untuk mencoba agent:
- "Berapa total penjualan bulan September?"
- "Berapa hari batas retur?"

## API (auth: `sanctum`)

Buat token:

```sh
php artisan tinker
$user = App\Models\User::where('email','admin@irnis.test')->first();
$user->createToken('dev')->plainTextToken;
```

```sh
# Chat
curl -X POST http://localhost:8000/api/chat \
  -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"message":"Berapa hari batas retur?"}'

# Transcript percakapan
curl http://localhost:8000/api/conversations -H "Authorization: Bearer <TOKEN>"
curl http://localhost:8000/api/conversations/1   -H "Authorization: Bearer <TOKEN>"

# Upload dokumen ke basis pengetahuan
curl -X POST http://localhost:8000/api/knowledge/documents \
  -H "Authorization: Bearer <TOKEN>" \
  -F "file=@sop-cuti.docx" -F "title=SOP Cuti" -F "source=Upload Manual"
```

## Perintah CLI

```sh
php artisan knowledge:index path/ke/sop.pdf --source="SOP Internal"
php artisan db:seed                      # idempoten; aman dijalankan ulang
php artisan test --compact               # suite deterministik (tanpa live)
```

## Testing

Suite berjalan di database PostgreSQL `irnis_ai_test` (konfigurasi di `phpunit.xml`). HTTP ke Ollama dimock kecuali test live:

```sh
php artisan test --compact

# Live end-to-end (butuh Ollama berjalan; chat + RAG asli)
$env:OLLAMA_LIVE_TEST="true"; php artisan test tests/Feature/LiveChatE2ETest.php   # PowerShell
OLLAMA_LIVE_TEST=true php artisan test tests/Feature/LiveChatE2ETest.php            # bash
```

Struktur utama: `app/Agents/AgentRunner.php` (loop agent), `app/Services/` (Ollama, Embedding, Knowledge, DocumentExtractor, TextChunker, Audit), `app/Tools/` (tool + registry), `app/Http/Controllers/` (API & dashboard), `resources/views/dashboard/**` (Blade), `database/seeders/` (demo).

Detail rencana produk: `PRD_IRNIS_AI_CORE_Roadmap.md`.

## Lisensi

Proyek internal/riset. Kode dasar Laravel berlisensi MIT.