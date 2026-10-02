# Whisper — Anonymous Messaging & Discussion Platform
### High-Performance PHP 8.5 Backend Architecture with MySQL 8.0 & DDD Principles

---

## 🌟 Executive Summary

**Whisper** is a production-grade anonymous messaging and threaded discussion platform engineered specifically to showcase Senior / Principal Backend Engineering and System Architecture capabilities.

Built with **modern PHP 8.5** and **MySQL 8.0 (InnoDB)**, Whisper avoids heavy third-party framework bloat while providing a strict **Domain-Driven Design (DDD)** and **Clean / Hexagonal Architecture** with zero dependencies.

---

## 🚀 Key Architectural Highlights

### 1. Hierarchical Nested Comments ($O(N)$ Zero $N+1$ Queries)
- Supports arbitrary depth commentary (**Post → Comment → Reply → Sub-Reply...**).
- Implements a hybrid **Adjacency List + Materialized Path** database pattern.
- Reconstructs the complete hierarchical nested tree from a single indexed SQL query in **linear time and memory $O(N)$** using pointer references.

### 2. Polymorphic Idempotent Likes
- Polymorphic target design supporting both `post` and `comment` targets.
- Composite unique key `UNIQUE KEY (target_type, target_id, user_fingerprint)` guaranteeing database-level idempotency and zero duplicate likes.
- Atomic counter caching with strict ACID database transactions (`GREATEST(0, likes_count + :delta)`).

### 3. Cryptographic Anonymous Identity & Author Ownership
- Users interact anonymously without account sign-ups.
- Deterministic HMAC-SHA256 fingerprinting generates unique human-readable pseudonyms (e.g., `Mystic Falcon #4829`) and vibrant gradient badges.
- When an anonymous user creates a whisper or comment, they receive a cryptographic **HMAC Ownership Token**. This allows the author to delete or edit their content securely without a user account or password.

### 4. Hacker News Trending / Hot Ranking Algorithm
- Dynamically computes whisper hot scores using a gravity decay formula:
  $$\text{Hot Score} = \frac{\text{Likes} + (\text{Comments} \times 2.0)}{\left(\max\left(0.1, \frac{\text{Age in Seconds}}{3600}\right) + 2.0\right)^{1.5}}$$

### 5. Sliding-Window Rate Limiting
- Defense-in-depth protection against spam and automated bots using a sliding window algorithm.
- Configurable per-action thresholds (Posts, Comments, Likes, Global).

---

## 🏗️ Architecture & Directory Structure

```
whisper/
├── app/
│   ├── Core/                           # Core Framework & Foundation
│   │   ├── Application.php             # DI Container Bootstrap & Pipeline Dispatcher
│   │   ├── Autoloader.php              # PSR-4 Compliant Autoloader
│   │   ├── Container.php               # Dependency Injection Container with Auto-wiring
│   │   ├── Database.php                # Multi-Driver Connection Manager (MySQL / SQLite)
│   │   ├── Request.php                 # HTTP Request Abstraction & Token Resolver
│   │   ├── Response.php                # Structured JSON Responses & Pagination Envelopes
│   │   ├── Router.php                  # Parametric Regex Router & Middleware Pipeline
│   │   ├── Middleware/                 # CORS & Security Headers Middlewares
│   │   ├── Exception/                  # Domain & HTTP Exceptions Hierarchy
│   │   └── Cache/                      # File-based Caching System with Telemetry
│   ├── Domain/                         # Domain Layer (Core Business Rules)
│   │   ├── Entity/                     # Post, Comment, Like, Identity
│   │   ├── Repository/                 # Domain Repository Interfaces
│   │   └── ValueObject/                # Pseudonym, TargetType, FeedSort
│   ├── Infrastructure/                 # Concrete Technical Implementations
│   │   ├── Database/                   # Migrator, DatabaseSeeder
│   │   ├── Repository/                 # PostRepository, CommentRepository, LikeRepository, RateLimitRepository
│   │   └── Security/                   # IdentityManager, ContentModerator, RateLimiter
│   ├── Service/                        # Application / Use Case Layer
│   │   ├── PostService.php
│   │   ├── CommentService.php
│   │   ├── LikeService.php
│   │   └── AnalyticsService.php
│   └── Http/
│       └── Controller/                 # PostController, CommentController, LikeController, IdentityController, SystemController, DocsController
├── config/                             # App & Database Configurations
├── database/                           # MySQL & SQLite Schemas (DDL)
├── public/                             # Public Web Root & Single Entry Point
│   ├── index.php                       # Front Controller
│   ├── index.html                      # Clean, Responsive UI
│   ├── css/style.css                   # Custom CSS
│   └── js/app.js                       # Vanilla JavaScript Client
├── tests/
│   └── run_tests.php                   # Built-in Automated Test Suite (27 Unit/Integration Tests)
├── openapi.json                        # OpenAPI 3.0 API Specification
├── ARCHITECTURE.md                     # Deep Technical & System Design Blueprint
└── README.md
```

---

## 📡 RESTful API Endpoints

| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/api/posts` | Paginated feed with search, category filters, and sorting (`recent`, `hot`, `popular`, `discussed`) |
| `POST` | `/api/posts` | Create anonymous whisper post (returns post & ownership token) |
| `GET` | `/api/posts/{id}` | Get whisper with full hierarchical nested comments tree |
| `DELETE` | `/api/posts/{id}` | Delete whisper (requires author fingerprint or `X-Ownership-Token`) |
| `POST` | `/api/posts/{postId}/comments` | Add root comment to a post |
| `POST` | `/api/comments/{id}/replies` | Reply directly to a comment (Nested / Threaded Reply) |
| `DELETE` | `/api/comments/{id}` | Delete comment / reply |
| `POST` | `/api/likes/toggle` | Polymorphic toggle like for post or comment |
| `GET` | `/api/identity/me` | Resolve or inspect current anonymous session |
| `POST` | `/api/identity/refresh` | Generate new anonymous identity & pseudonym |
| `GET` | `/api/stats` | System metrics, database status, and cache hit ratio |
| `GET` | `/api/health` | Health check endpoint |
| `GET` | `/docs` | Interactive Swagger / OpenAPI 3.0 Documentation UI |

---

## 🧪 Running Tests

Whisper comes with a comprehensive automated test suite verifying all layers against the live database:

```bash
php tests/run_tests.php
```

Sample output:
```
======================================================
    WHISPER BACKEND ARCHITECTURE & SUITE TEST RUNNER    
    PHP Version: 8.5.10 | Driver: mysql
======================================================

[TEST GROUP 1: Database Migration & Schema Setup]
  ✔ PASS: Database migration and table creation executed cleanly
  ✔ PASS: MySQL Database contains generated tables

[TEST GROUP 2: Anonymous Cryptographic Identity]
  ✔ PASS: Identity has valid pseudonym format
  ✔ PASS: Identity generates avatar color gradients
  ✔ PASS: Ownership token verified successfully
  ✔ PASS: Invalid ownership token rejected

[TEST GROUP 3: Post Service & Feeds]
  ✔ PASS: Post created with status code and ownership token
  ✔ PASS: Feed returns created posts with pagination
  ✔ PASS: Author viewer state correctly flagged as owner for post 1
  ✔ PASS: Non-author viewer state correctly flagged as non-owner for post 2
  ✔ PASS: Category filter works accurately

[TEST GROUP 4: Hierarchical Comments & Infinite Tree Structure]
  ✔ PASS: Root comment created on post (Depth 0)
  ✔ PASS: Reply created to parent comment (Depth 1)
  ✔ PASS: Sub-reply created on nested comment (Depth 2)
  ✔ PASS: Tree returns single root comment with nested children
  ✔ PASS: Root comment contains nested child reply
  ✔ PASS: Child reply contains sub-nested reply (Level 2)
  ✔ PASS: Post comments_count updated atomically

[TEST GROUP 5: Polymorphic Likes & Idempotency]
  ✔ PASS: Liking a post returns is_liked = true and count = 1
  ✔ PASS: Unliking the post returns is_liked = false and count = 0
  ✔ PASS: Liking a comment works via polymorphic target

[TEST GROUP 6: Sliding-Window Rate Limiter]
  ✔ PASS: Rate limiter successfully blocked excessive requests

[TEST GROUP 7: Content Moderation & Anti-Spam]
  ✔ PASS: Validation rejects post with title below minimum length

[TEST GROUP 8: HTTP Pipeline & Full Controller Dispatch]
  ✔ PASS: HTTP GET /api/posts returns 200 OK
  ✔ PASS: HTTP GET /api/health returns 200 OK
  ✔ PASS: HTTP GET /api/stats returns system telemetry
  ✔ PASS: Database successfully re-seeded with realistic production data

======================================================
    TEST SUMMARY: 27 Passed | 0 Failed
======================================================
```

---

## 💻 Local Development & Server Launch

1. Start the PHP built-in server:
   ```bash
   php -S 127.0.0.1:8000 -t public public/index.php
   ```
2. Open your browser:
   - **Web Application:** `http://127.0.0.1:8000`
   - **Interactive Swagger Docs:** `http://127.0.0.1:8000/docs`
   - **System Telemetry:** `http://127.0.0.1:8000/api/stats`
