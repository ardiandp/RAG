# Cara Kerja AI Menjawab Pertanyaan Chat

Dokumen ini menjelaskan alur lengkap bagaimana AI di aplikasi IRNIS AI CORE
memproses pertanyaan dari pengguna dan menghasilkan jawaban.

## Ringkasan

AI tidak menjawab langsung dari "ingatan" saja. Setiap pertanyaan diproses
oleh sebuah **agent loop** yang dapat memanggil **tool** (fungsi) seperti
pencarian dokumen (RAG), ringkasan penjualan, dan lain-lain sebelum menyusun
jawaban akhir. Model LLM dijalankan secara lokal oleh **Ollama**.

```
User ──► Dashboard / API ──► ChatController ──► AgentRunner
                                                  │
                                                  ├─► Ollama (qwen2.5) ──► jawaban / pilih tool
                                                  ├─► ToolRegistry ──► sales / product / customer / knowledge
                                                  ├─► KnowledgeService ──► nomic-embed-text ──► pgvector
                                                  └─► AuditService ──► audit_logs
```

## 1. Request Masuk

- Endpoint: `POST /api/chat` (API) atau halaman **Chat** di dashboard.
- Controller: `App\Http\Controllers\ChatController@handle`
  (`app/Http/Controllers/ChatController.php`).
- Validasi input: `message` (wajib, maks 4000 karakter), `conversation_id`,
  `agent_id` (opsional).
- Controller meneruskan ke `App\Agents\AgentRunner::run()`.

## 2. Persiapan Konteks (AgentRunner)

`app/Agents/AgentRunner.php`:

1. **Ambil/buat Conversation** — jika `conversation_id` diberikan, dipakai
   percakapan lama; jika tidak, dibuat percakapan baru dengan judul dari
   potongan pesan.
2. **Cek kepemilikan** — non-admin hanya bisa mengakses percakapan miliknya.
3. **Tentukan Agent** — dari `agent_id` atau relasi conversation. Jika agent
   nonaktif (`is_active = false`), dilempar error.
4. **Buat AgentRun** — record dengan status `running`, lalu audit log
   `agent_run.started`.
5. **Simpan pesan user** ke tabel `messages`.
6. **Bangun history** — system prompt (dari agent, atau default) + seluruh
   riwayat pesan conversation (role `user`/`assistant`/`system`).

## 3. Loop Agent (maks `max_steps`)

Setiap langkah:

1. Kirim `history` + daftar **tool schemas** (difilter sesuai `agent->tools`)
   ke `OllamaService::chat()` → `POST {OLLAMA_URL}/api/chat` dengan model
   `qwen2.5:1.5b` (atau `agent->model` jika diisi).
2. Periksa response model:
   - **Ada `tool_calls`** → eksekusi tiap tool, hasilnya ditambahkan ke history
     sebagai pesan `role: tool`, dan dicatat di tabel `tool_calls` + audit.
   - **Tidak ada `tool_calls` tapi ada teks JSON mirip tool-call** → di-parse
     oleh `extractTextualToolCalls()` (fallback untuk model kecil yang menulis
     tool-call sebagai teks biasa).
   - **Tidak ada keduanya** → `content` menjadi **jawaban final**, loop berhenti.
3. Jika `max_steps` tercapai tanpa jawaban final, status run menjadi `stopped`
   dan jawaban diganti pesan fallback.

### Eksekusi Tool

- Tiap tool ditemukan via `ToolRegistry`, dicek **permission**-nya (Gate).
  Jika user tidak punya izin, hasil `denied` + audit `tool_call.denied`.
- Hasil tool (sukses/error/denied) selalu dikembalikan ke model agar ia bisa
  menyusun jawaban berdasarkan data nyata.

## 4. Tool yang Tersedia

| Tool | Fungsi |
|---|---|
| `search_knowledge` | RAG: embed pertanyaan → cari chunk mirip di pgvector → konteks untuk jawaban |
| `get_sales_summary` | Ringkasan penjualan |
| `get_products` | Daftar/info produk |
| `get_customers` | Data pelanggan |

Contoh RAG (`search_knowledge`):
1. Pertanyaan user di-embed dengan `nomic-embed-text` (768 dimensi).
2. Dicari chunk dokumen dengan jarak kosinus terdekat di kolom
   `document_chunks.embedding` (pgvector).
3. Chunk teratas dikembalikan sebagai konteks, lalu model menyusun jawaban
   berdasarkan konteks tersebut.

## 5. Finalisasi

- Jawaban final disimpan sebagai pesan `role: assistant` di conversation.
- `AgentRun` diupdate: status `success`/`stopped`/`failed`, output, waktu selesai.
- Audit log `agent_run.finished`.
- Response JSON: `{ "answer": "..." }`.

## Contoh Alur

> User: "Berapa hari batas retur?"

1. AgentRunner menyimpan pesan, membangun history.
2. Model memutuskan memanggil tool `search_knowledge` dengan query
   "batas retur".
3. `KnowledgeService` mencari chunk SOP yang relevan di pgvector.
4. Hasil chunk dikembalikan ke model sebagai pesan tool.
5. Model menyusun jawaban: "Batas retur adalah 7 hari..." — jawaban final.
6. Jawaban disimpan, run selesai, response dikirim ke user.

## Konfigurasi Terkait

Lihat `config/ollama.php` dan `.env`:

```env
OLLAMA_URL=http://127.0.0.1:11434
OLLAMA_MODEL=qwen2.5:1.5b
OLLAMA_EMBEDDING_MODEL=nomic-embed-text
OLLAMA_EMBEDDING_DIMENSIONS=768
OLLAMA_TIMEOUT=120
OLLAMA_TEMPERATURE=0.2
```
