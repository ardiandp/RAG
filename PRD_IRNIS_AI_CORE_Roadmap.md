# PRD --- IRNIS AI CORE

## Roadmap Pembangunan Agentic AI Lokal dengan Laravel, PostgreSQL, Ollama & Qwen

**Versi:** 1.0\
**Tanggal:** 6 Oktober 2026\
**Status:** Draft / Implementation Roadmap\
**Target Environment:** PC lokal Windows 64-bit\
**Hardware baseline:** AMD Ryzen 3 5300U, RAM 8 GB\
**Arsitektur utama:** Laravel + PostgreSQL + pgvector + Ollama + Qwen

------------------------------------------------------------------------

# 1. Ringkasan Produk

IRNIS AI CORE adalah fondasi Agentic AI yang berjalan secara lokal dan
menjadi engine yang nantinya dapat digunakan oleh berbagai aplikasi,
seperti IRNIS School, HRIS, CRM, Knowledge Base, Finance, Customer
Service, dan aplikasi bisnis lainnya.

Sistem menggunakan Laravel sebagai application/agent orchestration
layer, PostgreSQL sebagai database utama, pgvector untuk semantic
search/RAG, Ollama sebagai local LLM runtime, dan Qwen sebagai model
bahasa.

Prinsip utama:

1.  Model AI tidak mengakses database secara langsung.
2.  Laravel menjadi pengendali agent dan tool.
3.  Tool memiliki permission dan validasi.
4.  Agent memiliki batas jumlah langkah untuk mencegah infinite loop.
5.  Development dilakukan secara lokal dan ringan sesuai hardware 8 GB
    RAM.
6.  n8n, Redis, Docker, dan service tambahan tidak menjadi dependency
    pada fase awal.

------------------------------------------------------------------------

# 2. Tujuan Produk

## 2.1 Tujuan Utama

Membangun Agentic AI lokal yang mampu:

-   menerima pertanyaan pengguna;
-   menggunakan Qwen melalui Ollama;
-   memahami kebutuhan pengguna;
-   memilih tool yang tersedia;
-   menjalankan tool melalui Laravel;
-   mengambil data dari PostgreSQL;
-   menggunakan knowledge base melalui pgvector;
-   menyimpan percakapan dan riwayat agent;
-   menghasilkan jawaban berdasarkan hasil tool/knowledge;
-   mencatat seluruh proses agent untuk audit dan debugging.

## 2.2 Non-Goals Fase Awal

Tidak termasuk pada fase awal:

-   training/fine-tuning model;
-   multi-agent kompleks;
-   deployment production berskala besar;
-   Kubernetes;
-   Elasticsearch;
-   workflow automation n8n;
-   WhatsApp automation;
-   distributed inference;
-   menjalankan banyak model besar secara bersamaan.

------------------------------------------------------------------------

# 3. Arsitektur Target

``` text
                         USER
                           |
                           v
                    +-------------+
                    |   Laravel   |
                    | API + Auth  |
                    +------+------+
                           |
                           v
                  +----------------+
                  |  Agent Engine  |
                  | Agent Runner   |
                  +-------+--------+
                          |
             +------------+-------------+
             |            |             |
             v            v             v
         PostgreSQL     Tools        Knowledge
             |            |             |
             |            |          pgvector
             |            |             |
             +------------+-------------+
                          |
                          v
                       Ollama
                          |
                          v
                         Qwen
```

------------------------------------------------------------------------

# 4. Prinsip Arsitektur

## Laravel

Bertanggung jawab terhadap:

-   API;
-   authentication;
-   authorization;
-   agent orchestration;
-   tool registry;
-   tool execution;
-   conversation;
-   memory;
-   audit;
-   business logic.

## PostgreSQL

Menyimpan:

-   users;
-   agents;
-   conversations;
-   messages;
-   agent runs;
-   tools;
-   tool calls;
-   knowledge sources;
-   documents;
-   document chunks;
-   embeddings;
-   audit logs.

## Ollama

Bertanggung jawab menjalankan model secara lokal.

## Qwen

Bertindak sebagai reasoning/LLM layer.

## pgvector

Digunakan untuk semantic search pada knowledge base.

------------------------------------------------------------------------

# 5. Roadmap Tahap Implementasi

  Tahap   Nama                  Output Utama
  ------- --------------------- ---------------------------------
  01      Local Foundation      Laravel + PostgreSQL siap
  02      Database Connection   Laravel terhubung PostgreSQL
  03      Ollama & Qwen         Local LLM berjalan
  04      LLM Gateway           Laravel ↔ Ollama ↔ Qwen
  05      Chat API              Chat dasar
  06      Tool System           Tool registry & execution
  07      Agent Loop            Agent dapat mengambil keputusan
  08      Memory                Conversation & memory
  09      RAG                   Knowledge base + pgvector
  10      Security              Auth, permission, validation
  11      Observability         Agent run & audit
  12      Dashboard             UI untuk mengelola agent

------------------------------------------------------------------------

# TAHAP 01 --- LOCAL FOUNDATION

## Tujuan

Menyiapkan environment development lokal yang ringan dan stabil.

## Komponen

-   PHP;
-   Composer;
-   Laravel;
-   PostgreSQL;
-   Git;
-   VS Code atau editor lain.

## Batasan

Belum menggunakan:

-   Ollama;
-   Qwen;
-   Redis;
-   n8n;
-   Docker.

## Output

Project Laravel dapat dijalankan di localhost.

Contoh:

``` text
http://localhost
```

## Acceptance Criteria

-   PHP dapat dijalankan dari terminal.
-   Composer berjalan.
-   Laravel berhasil dibuat.
-   Laravel dapat dijalankan.
-   Git repository tersedia.
-   Project memiliki `.env`.
-   Tidak ada error startup.

## Struktur Awal

``` text
irnis-ai/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/
├── resources/
├── routes/
├── storage/
├── tests/
├── .env
└── composer.json
```

------------------------------------------------------------------------

# TAHAP 02 --- DATABASE CONNECTION

## Tujuan

Menghubungkan Laravel dengan PostgreSQL.

## Database

``` text
database: irnis_ai
```

## Initial Tables

Tahap ini hanya membuat struktur inti:

``` text
users
agents
conversations
messages
agent_runs
tools
tool_calls
```

## Relasi Konsep

``` text
User
 |
 +-- Conversations
       |
       +-- Messages
       |
       +-- Agent Runs
              |
              +-- Tool Calls

Agent
 |
 +-- Agent Runs

Tools
 |
 +-- Tool Calls
```

## Acceptance Criteria

-   Laravel berhasil connect PostgreSQL.
-   Migration berhasil.
-   Model Eloquent berhasil membaca data.
-   Database dapat di-reset dan migration dapat dijalankan ulang.

------------------------------------------------------------------------

# TAHAP 03 --- OLLAMA & QWEN

## Tujuan

Menjalankan LLM secara lokal.

## Arsitektur

``` text
Laravel
   |
   v
Ollama
   |
   v
Qwen
```

## Persyaratan

Gunakan model Qwen yang realistis untuk RAM 8 GB dan prioritaskan model
quantized/kecil.

Jangan menjalankan model besar sebelum mengukur penggunaan RAM dan
respons.

## Acceptance Criteria

-   Ollama berjalan.
-   Model Qwen tersedia.
-   Model dapat menerima prompt.
-   Model menghasilkan response.
-   PC tetap usable selama pengujian.

------------------------------------------------------------------------

# TAHAP 04 --- LLM GATEWAY

## Tujuan

Membuat service Laravel khusus untuk komunikasi dengan Ollama.

## Service

``` text
app/Services/OllamaService.php
```

## Tanggung Jawab

-   mengirim prompt;
-   mengirim conversation context;
-   menerima response;
-   menangani timeout;
-   menangani error;
-   mencatat metadata dasar.

## Konfigurasi

Semua konfigurasi berada di `.env`.

Contoh konsep:

``` text
OLLAMA_URL=http://127.0.0.1:11434
OLLAMA_MODEL=<qwen-model>
OLLAMA_TIMEOUT=<value>
```

## Acceptance Criteria

Laravel dapat menjalankan:

``` text
ask("Apa itu PostgreSQL?")
```

dan mendapatkan jawaban dari Qwen.

------------------------------------------------------------------------

# TAHAP 05 --- CHAT API

## Tujuan

Membuat endpoint chat pertama.

## Endpoint

``` text
POST /api/chat
```

## Request

``` json
{
  "message": "Apa itu PostgreSQL?"
}
```

## Response

``` json
{
  "answer": "PostgreSQL adalah..."
}
```

## Alur

``` text
User
 |
 v
POST /api/chat
 |
 v
Chat Controller
 |
 v
OllamaService
 |
 v
Qwen
 |
 v
Response
```

## Acceptance Criteria

-   endpoint dapat menerima message;
-   response valid JSON;
-   error ditangani;
-   request dapat dicatat;
-   tidak ada API key LLM yang dikirim ke browser.

------------------------------------------------------------------------

# TAHAP 06 --- TOOL SYSTEM

## Tujuan

Mengubah chatbot menjadi agent yang dapat melakukan aksi.

## Konsep Tool

Setiap tool memiliki:

``` text
name
description
parameters
execute()
permission
```

## Contoh Tool

``` text
get_sales_summary
get_best_selling_product
get_customer_count
search_knowledge
```

## Interface Konsep

``` text
ToolInterface

- name()
- description()
- schema()
- execute()
```

## Tool Registry

Laravel memiliki daftar tool yang dapat digunakan agent.

``` text
Tool Registry
 |
 +-- SalesTool
 +-- ProductTool
 +-- CustomerTool
 +-- KnowledgeTool
```

## Aturan Penting

Qwen tidak boleh menjalankan SQL mentah secara bebas.

Contoh yang benar:

``` text
Qwen
 |
 | request get_sales_summary
 v
Laravel Tool
 |
 v
Validated Query
 |
 v
PostgreSQL
```

Bukan:

``` text
Qwen
 |
 | arbitrary SQL
 v
PostgreSQL
```

## Acceptance Criteria

-   tool dapat didaftarkan;
-   tool memiliki schema;
-   tool dapat dipanggil;
-   parameter divalidasi;
-   hasil tool dapat dikembalikan ke agent.

------------------------------------------------------------------------

# TAHAP 07 --- AGENT LOOP

## Tujuan

Membuat sistem yang mampu berpikir, menggunakan tool, membaca hasil, dan
melanjutkan proses.

## Agent Loop

``` text
User
 |
 v
Qwen
 |
 +---- No Tool ----> Final Answer
 |
 +---- Tool Needed
          |
          v
      Execute Tool
          |
          v
      Tool Result
          |
          v
         Qwen
          |
          +---- Tool Needed --> repeat
          |
          +---- No Tool ----> Final Answer
```

## Maximum Steps

Default:

``` text
MAX_AGENT_STEPS=5
```

Tujuannya:

-   mencegah infinite loop;
-   menghemat resource;
-   memudahkan debugging.

## Contoh

User:

``` text
Berapa penjualan September dan produk paling laku?
```

Agent:

``` text
Step 1
get_sales_summary()

Step 2
get_best_selling_product()

Step 3
Final answer
```

## Acceptance Criteria

-   agent dapat memilih tool;
-   tool dieksekusi;
-   hasil dikirim kembali ke Qwen;
-   agent dapat melakukan lebih dari satu tool call;
-   agent berhenti ketika jawaban selesai;
-   agent berhenti ketika mencapai batas step.

------------------------------------------------------------------------

# TAHAP 08 --- MEMORY

## Tujuan

Membuat agent mampu mempertahankan konteks percakapan.

## Tables

``` text
conversations
messages
agent_runs
```

## Message Types

``` text
system
user
assistant
tool
```

## Contoh

User:

``` text
Nama saya Budi.
```

Assistant:

``` text
Baik Budi.
```

User:

``` text
Berapa umur saya?
```

Agent memiliki context percakapan untuk menjawab berdasarkan informasi
yang tersedia.

## Memory Layer

Fase awal cukup menggunakan conversation history.

Memory jangka panjang dapat ditambahkan setelah core agent stabil.

## Acceptance Criteria

-   conversation dapat dibuat;
-   message disimpan;
-   history dapat diambil;
-   context dikirim ke Qwen;
-   beberapa turn percakapan berjalan dengan benar.

------------------------------------------------------------------------

# TAHAP 09 --- RAG + PGVECTOR

## Tujuan

Memberikan agent akses terhadap knowledge base.

## Sumber

-   PDF;
-   DOCX;
-   TXT;
-   artikel;
-   SOP;
-   dokumentasi;
-   knowledge internal.

## Pipeline

``` text
Document
 |
 v
Text Extraction
 |
 v
Chunking
 |
 v
Embedding
 |
 v
PostgreSQL + pgvector
```

## Query Pipeline

``` text
User Question
 |
 v
Embedding
 |
 v
Vector Search
 |
 v
Relevant Chunks
 |
 v
Qwen
 |
 v
Answer
```

## Tables

``` text
knowledge_sources
documents
document_chunks
```

Embedding disimpan pada `document_chunks`.

## Acceptance Criteria

-   document dapat diinput;
-   document dapat dipecah menjadi chunks;
-   embedding dapat dibuat;
-   embedding disimpan;
-   semantic search mengembalikan hasil relevan;
-   Qwen dapat menjawab berdasarkan knowledge.

------------------------------------------------------------------------

# TAHAP 10 --- SECURITY

## Tujuan

Memastikan agent tidak dapat melakukan aksi yang tidak diizinkan.

## Security Layer

``` text
Authentication
      |
      v
Authorization
      |
      v
Tool Permission
      |
      v
Parameter Validation
      |
      v
Tool Execution
```

## Aturan

1.  User harus authenticated untuk tool sensitif.
2.  Tool harus memiliki permission.
3.  Input tool harus divalidasi.
4.  Query database harus dikontrol aplikasi.
5.  Model tidak boleh memperoleh credential database.
6.  Secret tidak boleh dikirim ke browser.
7.  Agent harus memiliki batas step.
8.  Tool berbahaya harus memerlukan approval jika diperlukan.

## Acceptance Criteria

-   unauthorized user ditolak;
-   unauthorized tool call ditolak;
-   parameter invalid ditolak;
-   secret tidak masuk response API;
-   setiap aksi penting tercatat.

------------------------------------------------------------------------

# TAHAP 11 --- OBSERVABILITY & AUDIT

## Tujuan

Mengetahui apa yang dilakukan agent.

## Data yang Dicatat

``` text
agent_run
tool_call
tool_name
input
output
status
duration
step
error
created_at
```

## Contoh Log

``` text
Agent Run #102

Step 1
Tool: search_knowledge
Status: success
Duration: 240ms

Step 2
Tool: get_sales_summary
Status: success
Duration: 90ms

Step 3
Final Answer
```

## Manfaat

-   debugging;
-   monitoring;
-   audit;
-   evaluasi agent;
-   analisis biaya/resource;
-   mengetahui tool yang sering gagal.

## Acceptance Criteria

Setiap agent execution dapat ditelusuri dari awal sampai akhir.

------------------------------------------------------------------------

# TAHAP 12 --- DASHBOARD

## Tujuan

Membuat UI untuk mengelola Agentic AI.

## Dashboard Minimum

``` text
Dashboard
|
+-- Overview
+-- Agents
+-- Conversations
+-- Knowledge Base
+-- Tools
+-- Agent Runs
+-- Audit Logs
+-- Settings
```

## Dashboard Overview

Menampilkan:

-   total agents;
-   total conversations;
-   agent runs;
-   tool calls;
-   successful runs;
-   failed runs;
-   knowledge documents.

## Agent Management

Admin dapat:

-   membuat agent;
-   mengubah system prompt;
-   memilih tools;
-   mengaktifkan/nonaktifkan agent;
-   menentukan max steps.

## Knowledge Management

Admin dapat:

-   upload document;
-   melihat document;
-   menghapus document;
-   melihat status indexing.

## Agent Run Viewer

Menampilkan:

``` text
User Input
    |
    v
Step 1
    |
    v
Tool Call
    |
    v
Tool Result
    |
    v
Step 2
    |
    v
Final Answer
```

------------------------------------------------------------------------

# 6. Struktur Database Target

``` text
users
  |
  +-- conversations
          |
          +-- messages
          |
          +-- agent_runs
                  |
                  +-- tool_calls

agents
  |
  +-- agent_runs

tools
  |
  +-- tool_calls

knowledge_sources
  |
  +-- documents
          |
          +-- document_chunks
                    |
                    +-- embedding
```

------------------------------------------------------------------------

# 7. Struktur Folder Target

``` text
app/
├── Agents/
│   ├── AgentManager.php
│   ├── AgentRunner.php
│   └── AgentPrompt.php
│
├── Tools/
│   ├── Contracts/
│   │   └── ToolInterface.php
│   ├── SalesTool.php
│   ├── ProductTool.php
│   ├── CustomerTool.php
│   └── KnowledgeTool.php
│
├── Services/
│   ├── OllamaService.php
│   ├── EmbeddingService.php
│   └── KnowledgeService.php
│
├── Models/
│   ├── Agent.php
│   ├── AgentRun.php
│   ├── Conversation.php
│   ├── Message.php
│   ├── Tool.php
│   ├── ToolCall.php
│   ├── KnowledgeSource.php
│   ├── Document.php
│   └── DocumentChunk.php
│
└── Http/
    └── Controllers/
        ├── ChatController.php
        ├── AgentController.php
        └── KnowledgeController.php
```

------------------------------------------------------------------------

# 8. Agent Execution Flow Target

``` text
                    USER
                      |
                      v
                ChatController
                      |
                      v
                AgentRunner
                      |
                      v
                 Load Context
                      |
                      v
                    Qwen
                      |
              +-------+-------+
              |               |
           No Tool          Tool Call
              |               |
              v               v
        Final Answer      Validate Tool
                              |
                              v
                         Check Permission
                              |
                              v
                         Execute Tool
                              |
                              v
                         Save Result
                              |
                              v
                             Qwen
                              |
                    +---------+---------+
                    |                   |
                 Continue            Finish
                    |                   |
                    +--> Agent Loop     v
                                  Final Answer
```

------------------------------------------------------------------------

# 9. Contoh Use Case MVP

## Use Case 1 --- General Chat

User:

``` text
Apa itu Laravel?
```

Flow:

``` text
User
→ Laravel
→ Qwen
→ Answer
```

## Use Case 2 --- Database Tool

User:

``` text
Berapa total penjualan bulan September?
```

Flow:

``` text
User
→ Qwen
→ get_sales_summary
→ PostgreSQL
→ Qwen
→ Answer
```

## Use Case 3 --- Multi Tool

User:

``` text
Berapa penjualan September dan produk paling laku?
```

Flow:

``` text
Qwen
→ get_sales_summary
→ get_best_selling_product
→ Qwen
→ Answer
```

## Use Case 4 --- Knowledge

User:

``` text
Apa prosedur cuti berdasarkan SOP perusahaan?
```

Flow:

``` text
Qwen
→ search_knowledge
→ pgvector
→ SOP chunks
→ Qwen
→ Answer
```

------------------------------------------------------------------------

# 10. Definition of Done --- MVP

MVP dianggap berhasil apabila:

-   [ ] Laravel berjalan lokal.
-   [ ] PostgreSQL berjalan.
-   [ ] Laravel terhubung PostgreSQL.
-   [ ] Ollama berjalan.
-   [ ] Qwen dapat dipanggil.
-   [ ] Laravel dapat mengirim prompt ke Qwen.
-   [ ] Chat API tersedia.
-   [ ] Tool system tersedia.
-   [ ] Minimal 2 tool dapat digunakan.
-   [ ] Agent loop berjalan.
-   [ ] Agent memiliki max step.
-   [ ] Conversation tersimpan.
-   [ ] Agent run tersimpan.
-   [ ] Tool call tersimpan.
-   [ ] pgvector terpasang.
-   [ ] Minimal satu knowledge document dapat dicari.
-   [ ] Authentication tersedia.
-   [ ] Tool permission tersedia.
-   [ ] Audit log tersedia.
-   [ ] Dashboard dasar tersedia.

------------------------------------------------------------------------

# 11. Strategi Hardware 8 GB RAM

Karena development dilakukan pada RAM 8 GB, prioritasnya adalah menjaga
jumlah service tetap sedikit.

## Service Utama

``` text
Laravel
PostgreSQL
Ollama
Qwen
```

## Belum diperlukan

``` text
Redis
n8n
Elasticsearch
Docker
Kubernetes
Multiple LLM
```

## Prinsip

Jalankan satu model utama terlebih dahulu.

Gunakan model Qwen yang sesuai dengan kapasitas RAM.

Uji:

-   response time;
-   RAM usage;
-   context size;
-   stabilitas;
-   kualitas tool calling.

------------------------------------------------------------------------

# 12. Strategi Pengembangan

Jangan mengerjakan semua tahap sekaligus.

Setiap tahap harus menghasilkan kondisi yang bisa diuji.

``` text
Tahap 01
    ↓
Test
    ↓
Tahap 02
    ↓
Test
    ↓
Tahap 03
    ↓
Test
    ↓
...
    ↓
Tahap 12
```

Jika satu tahap gagal, jangan melanjutkan terlalu jauh sebelum
masalahnya dipahami.

------------------------------------------------------------------------

# 13. Prioritas Implementasi

## P0 --- Wajib

-   Laravel
-   PostgreSQL
-   Ollama
-   Qwen
-   LLM Gateway
-   Chat API
-   Tool Calling
-   Agent Loop

## P1 --- Sangat penting

-   Conversation
-   Memory
-   Authentication
-   Permission
-   Agent Run
-   Tool Call Log

## P2 --- Setelah Core Stabil

-   pgvector
-   RAG
-   Knowledge Base
-   Dashboard
-   Evaluation

## P3 --- Nanti

-   n8n
-   Redis
-   Queue
-   multi-agent
-   external APIs
-   production scaling

------------------------------------------------------------------------

# 14. Milestone

## Milestone A --- Local AI

Tahap 01--05

Hasil:

``` text
Laravel → Ollama → Qwen
```

## Milestone B --- Agent

Tahap 06--08

Hasil:

``` text
Laravel → Agent → Qwen → Tools → PostgreSQL
```

## Milestone C --- Knowledge Agent

Tahap 09

Hasil:

``` text
Agent → pgvector → Knowledge → Qwen
```

## Milestone D --- Secure Agent

Tahap 10--11

Hasil:

``` text
Authenticated Agent
+
Permission
+
Audit
```

## Milestone E --- Platform

Tahap 12

Hasil:

``` text
IRNIS AI CORE
```

------------------------------------------------------------------------

# 15. Pengembangan Setelah MVP

Setelah core stabil, baru pertimbangkan:

``` text
                IRNIS AI CORE
                       |
        +--------------+--------------+
        |              |              |
     IRNIS          IRNIS          IRNIS
     SCHOOL          HRIS           CRM
        |              |              |
        +--------------+--------------+
                       |
                    Agent Core
                       |
              +--------+--------+
              |        |        |
           Tools     Memory     RAG
```

n8n dapat ditambahkan sebagai integration/workflow layer:

``` text
IRNIS AI CORE
      |
      v
     n8n
      |
 +----+-----+--------+
 |          |        |
Gmail    WhatsApp  Drive
```

n8n bukan bagian wajib dari Agent Core.

------------------------------------------------------------------------

# 16. Kesimpulan

Produk dibangun secara bertahap dengan prinsip:

``` text
SIMPLE
  ↓
CONNECTED
  ↓
CHAT
  ↓
TOOLS
  ↓
AGENT
  ↓
MEMORY
  ↓
RAG
  ↓
SECURITY
  ↓
OBSERVABILITY
  ↓
DASHBOARD
```

Target akhirnya bukan sekadar chatbot, tetapi Agentic AI Core yang
dapat:

1.  memahami permintaan;
2.  mengambil keputusan;
3.  memilih tool;
4.  menjalankan tool;
5.  membaca hasil;
6.  menggunakan knowledge;
7.  mengingat konteks;
8.  menghasilkan jawaban;
9.  mencatat seluruh proses;
10. digunakan kembali oleh berbagai aplikasi.

------------------------------------------------------------------------

# 17. Next Action

Mulai implementasi dari:

**TAHAP 01 --- LOCAL FOUNDATION**

Checklist:

``` text
[ ] Cek PHP
[ ] Cek Composer
[ ] Cek Git
[ ] Install Laravel
[ ] Buat project irnis-ai
[ ] Jalankan Laravel
[ ] Buat repository Git
```

Setelah Tahap 01 berhasil, lanjut:

**TAHAP 02 --- PostgreSQL Connection**

Jangan mengimplementasikan Ollama/Qwen sebelum Laravel dan database
dasar stabil.
