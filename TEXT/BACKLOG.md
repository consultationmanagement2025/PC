# Project Backlog - PCMS

**Project**: Public Consultation Management Portal (PCMS)  
**Organization**: City of Valenzuela, Metropolitan Manila  
**Last Updated**: January 26, 2026  
**Status**: Active Development

---

## Table of Contents
1. [System Architecture & Diagrams](#system-architecture--diagrams)
2. [Use Cases & ERD](#use-cases--system-architecture)
3. [Product Backlog](#product-backlog)
4. [Sprint Priorities](#sprint-priorities)
5. [Technical Debt](#technical-debt)
6. [Bug Fixes](#bug-fixes)
7. [Performance Improvements](#performance-improvements)

---

## System Architecture & Diagrams

### 📊 Diagram Index

```
Available Diagrams:
├── DFD (Data Flow Diagrams)
│   ├── Level 0 - Context Diagram
│   ├── Level 1 - Main Processes
│   └── Level 2 - Detailed Processes
├── BPA (Business Process Analysis) - Level 2
├── BPMN (Business Process Model & Notation) with Integration
├── Use Case Diagram (with Actor Mapping)
├── Flowchart (System Workflows)
├── Micro API Architecture
├── Micro Services Communication
├── API Pipeline & Data Flow
└── ERD (Entity-Relationship Diagram)
```

---

### 1️⃣ **DFD Level 0 - Context Diagram**

```
                          ┌──────────────────────────────┐
                          │  PCMS System                 │
                          │  (Single Entity)             │
                          └──────────┬───────────────────┘
                                     │
                ┌────────────────────┼────────────────────┐
                │                    │                    │
                ▼                    ▼                    ▼
        ┌──────────────┐    ┌──────────────┐    ┌──────────────┐
        │    Citizens  │    │ Portal Staff │    │  Admin Users │
        │              │    │              │    │              │
        │ • Submit     │    │ • Upload     │    │ • Monitor    │
        │   feedback   │    │   documents  │    │   system     │
        │ • View docs  │    │ • Manage     │    │ • Manage     │
        │ • Submit      │    │   staff      │    │   users      │
        │   feedback    │    │ • Manage     │    │ • View audit │
        └──────────────┘    │   users      │    │   logs       │
                            └──────────────┘    └──────────────┘
```

---

### 2️⃣ **DFD Level 1 - Main Processes**

```
                          Input: Posts, Comments, Users
                                    │
                    ┌───────────────┼───────────────┐
                    │               │               │
            ┌───────▼──────┐ ┌──────▼──────┐ ┌─────▼───────┐
            │  1.0          │ │  2.0        │ │  3.0        │
            │ Consultation  │ │ User & Auth │ │ Notification│
            │ Management    │ │ Management  │ │ System      │
            └───────┬──────┘ └──────┬──────┘ └─────┬───────┘
                    │               │               │
                    ▼               ▼               ▼
            ┌───────────────────────────────────────────────┐
            │          4.0 Audit Logging                    │
            │  (Tracks all actions across system)           │
            └───────────────────────────────────────────────┘
                    │
                    ▼
            ┌───────────────────────────────────────────────┐
            │      5.0 Report & Analytics Generation        │
            │  (Generates reports from audit logs)          │
            └───────────────────────────────────────────────┘
```

---

### 3️⃣ **DFD Level 2 - Consultation Post Submission (Detailed)**

```
Input: Citizen submits consultation/feedback
       │
       ▼
┌─────────────────────┐
│ 1.1 Validate        │
│ Post Content        │
└──────────┬──────────┘
           │
       ✓ Yes
           │
           ▼
┌─────────────────────┐
│ 1.2 Store to        │
│ Database            │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ 1.3 Create Audit    │
│ Log Entry           │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ 1.4 Tag Post with   │
│ Category/Topic      │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ 1.5 Notify Staff    │
│ of New Post         │
└──────────┬──────────┘
           │
       Output: Document Stored, Indexed, Users Notified
```

---

### 4️⃣ **BPA Level 2 - Business Process Analysis**

```
PROCESS: Consultation Post Moderation Workflow

┌────────────────────────────────────────────────────────────┐
│ Process Owner: Moderator       Priority: HIGH              │
│ Frequency: Continuous          Duration: 1-4 hours         │
├────────────────────────────────────────────────────────────┤

TRIGGER: Citizen submits post/feedback

STEPS:
  1. Citizen submits post/feedback
     └─ Duration: 2 min
     └─ Resources: Citizen, System

  2. Staff member receives notification
     └─ Duration: Real-time
     └─ Resources: Email/In-app notification

  3. Moderator reviews content
     └─ Duration: 30 min - 1 hour
     └─ Resources: Manager, Document

  4. Decision point:
     ├─ APPROVED → Step 5
     ├─ REJECTED → Step 6
     └─ FLAGGED → Step 7

  5. Publish post/feedback
     └─ Duration: 5 min
     └─ Resources: System, Staff
     └─ Output: Published, Notify citizen and stakeholders

  6. Reject with feedback
     └─ Duration: 5 min
     └─ Resources: System
     └─ Output: Citizen notified, Request revision or clarification

  7. Flag for review
     └─ Duration: 5 min
     └─ Resources: System
     └─ Output: Escalated to manager for further review

END: Post status updated, Actions logged

KPIs:
  • Moderation time: < 4 hours (Target)
  • Approval rate: > 80%
  • Rejection rate: < 10%
  • Flag rate: < 10%
└────────────────────────────────────────────────────────────┘
```

---

### 5️⃣ **BPMN - Business Process Model & Notation (with Integration)**

```
START
  │
  ▼
┌─────────────────┐
│ Citizen submits │ ◄──────── Integration: Post Submission Service
│ Post/Feedback   │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────┐
│ Validate post content           │ ◄──── Integration: Content Validator API
│ (No spam, profanity check)      │
└────────┬────────────────────────┘
         │
    ┌────┴──────┐
    │ Yes   No  │
    ▼    ▼
  ✓     ✗ Reject
    │    │
    │    └─────────┐
    │              │
    ▼              ▼
   ┌─────────────────────────────────┐
   │ Send notification to Citizen     │ ◄──── Integration: Email/SMS Service
   │ (Post rejected)                  │
   └─────────────────────────────────┘
    │
    ▼ (After revision)
┌─────────────────────┐
│ Store to Database   │ ◄────────── Integration: Database Service
│                     │
└────────┬────────────┘
         │
         ▼
┌──────────────────────────────────────┐
│ Tag & Categorize Post                │ ◄──── Integration: Search Service
│ (Topic, Sentiment, Keywords)         │
└────────┬─────────────────────────────┘
         │
         ▼
┌──────────────────────────────────────┐
│ Log action in Audit Trail            │ ◄──── Integration: Logging Service
│                                      │
└────────┬─────────────────────────────┘
         │
         ▼
┌──────────────────────────────────────┐
│ Notify Moderators for review         │ ◄──── Integration: Notification Service
│                                      │
└────────┬─────────────────────────────┘
         │
    ┌────┴──────────────┐
    │ Moderator action  │
    ▼                   ▼
  APPROVE             REJECT
    │                   │
    │                   └─────────────┐
    │                                 │
    ▼                                 ▼
┌────────────────────┐   ┌─────────────────────┐
│ Publish Post       │   │ Return for revision │
│                    │   │ (Request clarif.)   │
└────────┬───────────┘   └────────┬────────────┘
         │                        │
         ▼                        ▼
┌──────────────────────────────────────┐
│ Notify stakeholders & followers      │ ◄──── Integration: Broadcast Service
│                                      │
└────────┬─────────────────────────────┘
         │
         ▼
       END
```

---

### 6️⃣ **Flowchart - Citizen Post Submission Workflow**

```
                              START
                                │
                                ▼
                    ┌───────────────────────┐
                    │ Citizen opens         │
                    │ consultation/feedback │
                    │ form                  │
                    └───────────┬───────────┘
                                │
                                ▼
                    ┌───────────────────────┐
                    │ Fill post form        │
                    │ (Opinion, Rating)     │
                    └───────────┬───────────┘
                                │
                                ▼
                    ┌───────────────────────┐
              ┌─────┤ Valid submission?     │
              │ No  └───────────┬───────────┘
              │                 │ Yes
              │                 ▼
              │     ┌───────────────────────┐
              │     │ Store post in DB      │
              │     └───────────┬───────────┘
              │                 │
              │                 ▼
              │     ┌───────────────────────┐
              │     │ Create notification   │
              │     │ for moderators        │
              │     └───────────┬───────────┘
              │                 │
              │                 ▼
              │     ┌───────────────────────┐
              │     │ Log audit event       │
              │     └───────────┬───────────┘
              │                 │
              │                 ▼
              │     ┌───────────────────────┐
              │     │ Show success message  │
              │     └───────────┬───────────┘
              │                 │
              └─────────────┬───┘
                            │
                            ▼
                        [END]
                     Post submitted for review
```

---

### 7️⃣ **Microservices API Architecture**

```
┌─────────────────────────────────────────────────────────────────┐
│                     CLIENT LAYER                                 │
│  (Web Browser, Mobile, External Systems)                        │
└─────────────────────────┬───────────────────────────────────────┘
                          │
                          ▼
        ┌─────────────────────────────────────┐
        │    API GATEWAY / Load Balancer      │
        │  (Routes requests to services)      │
        └─────────────────────────────────────┘
                    │    │    │    │    │
        ┌───────────┼────┼────┼────┼────┼────────────┐
        │           │    │    │    │    │            │
        ▼           ▼    ▼    ▼    ▼    ▼            ▼
    ┌───────┐ ┌────────┐ ┌────────┐ ┌─────┐ ┌──────────┐ ┌─────┐
    │ Auth  │ │Post/   │ │Comment │ │User │ │Audit Log │ │Notif│
    │Service│ │Feedback│ │Service │ │Mgmt │ │Service   │ │ica- │
    │       │ │Service │ │        │ │Serv │ │          │ │tion │
    └───────┘ └────────┘ └────────┘ └─────┘ └──────────┘ └─────┘
        │          │         │         │          │           │
        └──────────┴─────────┴─────────┴──────────┴───────────┘
                          │
                          ▼
        ┌─────────────────────────────────────┐
        │    DATABASE LAYER                   │
        │  (MySQL, PostgreSQL)                │
        └─────────────────────────────────────┘
```

---

### 8️⃣ **Microservices Communication Pattern**

```
SYNCHRONOUS (Request-Response) - REST/gRPC
                │
                ▼
    ┌──────────────────────────┐
    │ Service A                │
    │  (Document Service)      │
    └──────────┬───────────────┘
               │
          HTTP GET /documents
               │
               ▼
    ┌──────────────────────────┐
    │ Service B                │
    │  (Search Service)        │
    │  Query: "annual report"  │
    └──────────┬───────────────┘
               │
        JSON Response
               │
               ▼
    ┌──────────────────────────┐
    │ Results displayed        │
    └──────────────────────────┘

═══════════════════════════════════════════════════════════

ASYNCHRONOUS (Event-Driven) - Message Queue
                │
                ▼
    ┌──────────────────────────┐
    │ Service A                │
    │  (Document Service)      │
    │ Publishes: "doc.created" │
    └──────────┬───────────────┘
               │
          EVENT MESSAGE
               │
          ┌────▼──────────┐
          │ Message Queue │
          │  (RabbitMQ/   │
          │   Kafka)      │
          └────┬──────────┘
               │
    ┌──────────┼──────────────┐
    │          │              │
    ▼          ▼              ▼
┌────────┐ ┌──────────┐ ┌──────────┐
│Service │ │ Service  │ │ Service  │
│   B    │ │    C     │ │    D     │
│Notifi- │ │ Indexing │ │ Analytics│
│cation  │ │ Service  │ │ Service  │
└────────┘ └──────────┘ └──────────┘

(Services handle events independently)
```

---

### 9️⃣ **API Pipeline & Data Flow**

```
REQUEST FLOW (Incoming)
═════════════════════════════════════════════════════════════

1. Client Request
   │
   ▼
2. API Gateway
   ├─ Validate token/authentication
   ├─ Rate limiting check
   └─ Request logging
   │
   ▼
3. Route to Service
   ├─ Identify target service
   └─ Load balance
   │
   ▼
4. Service Handler
   ├─ Validate input parameters
   ├─ Check user permissions
   └─ Business logic
   │
   ▼
5. Database Operations
   ├─ Query/Insert/Update
   ├─ Transaction management
   └─ Index updates
   │
   ▼
6. Cache Layer (Optional)
   ├─ Store frequently accessed data
   └─ TTL management
   │
   ▼
7. Response Formation
   ├─ Serialize response
   ├─ Add metadata
   └─ Compress (if needed)
   │
   ▼
8. Return to Client
   ├─ HTTP status code
   ├─ Headers
   └─ JSON/XML body


RESPONSE FLOW (Outgoing)
═════════════════════════════════════════════════════════════

Response Ready
   │
   ▼
Post-Process
├─ Log action (Audit)
├─ Trigger webhooks
└─ Send notifications
   │
   ▼
Queue Async Tasks
├─ Email notifications
├─ Report generation
└─ Analytics updates
   │
   ▼
Return to Client
├─ 200 OK
├─ 400 Bad Request
├─ 401 Unauthorized
├─ 403 Forbidden
├─ 404 Not Found
├─ 500 Server Error
   │
   ▼
Client receives response
```

---

### 🔟 **Complete Data Flow Example: Document Upload**

```
┌──────────────────────────────────────────────────────────────┐
│                   DOCUMENT UPLOAD FLOW                        │
├──────────────────────────────────────────────────────────────┤

INPUT: User uploads "annual_report.pdf"
         │
         ▼
    ┌────────────────────┐
    │ 1. API Gateway     │ ✓ Authenticate user
    │    Middleware      │ ✓ Rate limit check
    │                    │ ✓ File type whitelist
    └─────────┬──────────┘
              │
              ▼
    ┌────────────────────────────────┐
    │ 2. Document Service            │
    │ ├─ Validate file size          │
    │ ├─ Scan for viruses (optional) │
    │ └─ Generate file hash          │
    └─────────┬──────────────────────┘
              │
              ▼
    ┌────────────────────────────────┐
    │ 3. File Storage Service        │
    │ ├─ Store file in storage       │
    │ ├─ Create backup               │
    │ └─ Return file URL             │
    └─────────┬──────────────────────┘
              │
              ▼
    ┌────────────────────────────────┐
    │ 4. Database Service            │
    │ ├─ Insert document record      │
    │ ├─ Store metadata              │
    │ └─ Return doc_id               │
    └─────────┬──────────────────────┘
              │
              ▼
    ┌────────────────────────────────┐
    │ 5. Audit Log Service           │
    │ ├─ Log upload action           │
    │ ├─ Store user ID, timestamp    │
    │ └─ Mark action type            │
    └─────────┬──────────────────────┘
              │
              ▼
    ┌────────────────────────────────┐
    │ 6. Search/Index Service        │
    │ ├─ Extract text from PDF       │
    │ ├─ Create searchable index     │
    │ └─ Auto-tag document           │
    └─────────┬──────────────────────┘
              │
              ▼
    ┌────────────────────────────────┐
    │ 7. Notification Service        │
    │ ├─ Notify managers for review  │
    │ ├─ Notify stakeholders         │
    │ └─ Queue email tasks           │
    └─────────┬──────────────────────┘
              │
              ▼
    ┌────────────────────────────────┐
    │ 8. Analytics Service           │
    │ ├─ Update upload metrics       │
    │ ├─ Track user activity         │
    │ └─ Update category stats       │
    └─────────┬──────────────────────┘
              │
              ▼
        ✓ Response sent to client
        ├─ Status: 201 Created
        ├─ Document ID
        └─ File URL
└──────────────────────────────────────────────────────────────┘

TIME: ~500-800ms (depending on file size)
ASYNC TASKS: Email notification, Full-text indexing, Analytics
```

---

## Use Cases & System Architecture

### 👥 Actors

```
┌─────────────────────────────────────────────────────────────┐
│                     SYSTEM ACTORS                            │
├─────────────────────────────────────────────────────────────┤
│ 👤 CITIZEN      │ Public user accessing documents & feedback  │
│ 👤 STAFF       │ Government staff managing documents          │
│ 👤 MANAGER     │ Department manager with approval authority  │
│ 👤 ADMIN       │ System administrator - full access          │
└─────────────────────────────────────────────────────────────┘
```

### 🎯 Use Case Diagram Overview

```
                           ┌─────────────────────────────────┐
                           │   PCMS/LLRM SYSTEM              │
                           │                                 │
                    ┌──────┼─────────┬──────────┬────────┐   │
                    │      │         │          │        │   │
              ┌─────▼─┐ ┌──▼───┐ ┌──▼───┐ ┌───▼─┐ ┌─────▼┐ │
              │Upload │ │Create│ │View  │ │Mgmt │ │Audit │ │
              │Docs   │ │Anno- │ │Anno- │ │User │ │Logs  │ │
              └─┬─────┘ │unce- │ └──┬───┘ └─┬───┘ └──┬───┘ │
                │       │ments │    │       │       │      │
              ┌─▼─┐ ┌───▼──┐ ┌─▼──┐ ┌──▼──┐ ┌──▼──┐        │
              │Tag│ │Track │ │Post│ │Appr-│ │Gener│ Filter │
              │Docs│ │Status│ │Cmnt│ │ove  │ │ Rate│ Logs   │
              └───┘ └──────┘ └────┘ └─────┘ └─────┘        │
                    │         │       │      │              │
                    ▼         ▼       ▼      ▼              │
              ┌────────────────────────────────┐            │
              │    CORE DATABASE               │            │
              │   (Users, Documents, Posts)    │            │
              └────────────────────────────────┘            │
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
    ┌───▼─┐           ┌────▼────┐       ┌────▼───┐
    │ 👤  │           │   👤    │       │  👤   │
    │CITIZEN          │  STAFF  │       │MANAGER │ ADMIN
    └──────┘           └─────────┘       └────────┘
```

### 📋 Use Cases by Category

#### 1️⃣ **Document & Legislative Management**
| UC# | Use Case | Citizen | Staff | Manager | Admin |
|-----|----------|:-------:|:-----:|:-------:|:-----:|
| UC-1 | Upload/Encode Documents | | ✓ | ✓ | ✓ |
| UC-2 | Create Document Versions | | ✓ | ✓ | ✓ |
| UC-3 | Index & Tag Documents | | ✓ | ✓ | ✓ |
| UC-4 | Search & Browse Documents | ✓ | ✓ | ✓ | ✓ |
| UC-5 | Download/Print Documents | ✓ | ✓ | ✓ | ✓ |
| UC-6 | Track Document Status | ✓ | ✓ | ✓ | ✓ |
| UC-7 | Approve/Reject Documents | | | ✓ | ✓ |

#### 2️⃣ **Announcement & Communication**
| UC# | Use Case | Citizen | Staff | Manager | Admin |
|-----|----------|:-------:|:-----:|:-------:|:-----:|
| UC-8 | Create Announcements | | ✓ | ✓ | ✓ |
| UC-9 | Schedule Announcements | | ✓ | ✓ | ✓ |
| UC-10 | Target Announcements by Role/Dept | | ✓ | ✓ | ✓ |
| UC-11 | View Announcements | ✓ | ✓ | ✓ | ✓ |

#### 3️⃣ **Public Consultation & Feedback**
| UC# | Use Case | Citizen | Staff | Manager | Admin |
|-----|----------|:-------:|:-----:|:-------:|:-----:|
| UC-12 | Submit Consultation Feedback | ✓ | | | |
| UC-13 | Post Comments on Documents | ✓ | ✓ | ✓ | ✓ |
| UC-14 | View Consultation Progress | ✓ | ✓ | ✓ | ✓ |
| UC-15 | Track Legislation Timeline | ✓ | ✓ | ✓ | ✓ |

#### 4️⃣ **User & Access Management**
| UC# | Use Case | Citizen | Staff | Manager | Admin |
|-----|----------|:-------:|:-----:|:-------:|:-----:|
| UC-16 | Register/Create User Account | ✓ | | | ✓ |
| UC-17 | Manage User Roles & Permissions | | | ✓ | ✓ |
| UC-18 | Create Custom Roles | | | | ✓ |
| UC-19 | Assign User Groups/Teams | | | ✓ | ✓ |
| UC-20 | Reset User Password | | | ✓ | ✓ |

#### 5️⃣ **Notifications & Alerts**
| UC# | Use Case | Citizen | Staff | Manager | Admin |
|-----|----------|:-------:|:-----:|:-------:|:-----:|
| UC-21 | Receive In-App Notifications | ✓ | ✓ | ✓ | ✓ |
| UC-22 | Receive Email Notifications | ✓ | ✓ | ✓ | ✓ |
| UC-23 | Set Notification Preferences | ✓ | ✓ | ✓ | ✓ |
| UC-24 | Track Document Mentions | ✓ | ✓ | ✓ | ✓ |

#### 6️⃣ **Reporting & Analytics**
| UC# | Use Case | Citizen | Staff | Manager | Admin |
|-----|----------|:-------:|:-----:|:-------:|:-----:|
| UC-25 | Generate Activity Reports | | | ✓ | ✓ |
| UC-26 | Generate Consultation Reports | | | ✓ | ✓ |
| UC-27 | Export Data (PDF/Excel/CSV) | | | ✓ | ✓ |
| UC-28 | View System Analytics Dashboard | | | ✓ | ✓ |

#### 7️⃣ **Audit & Compliance**
| UC# | Use Case | Citizen | Staff | Manager | Admin |
|-----|----------|:-------:|:-----:|:-------:|:-----:|
| UC-29 | View Audit Logs | | | ✓ | ✓ |
| UC-30 | Filter Audit Logs by User/Action | | | ✓ | ✓ |
| UC-31 | Track User Login History | | | ✓ | ✓ |
| UC-32 | Export Audit Reports | | | ✓ | ✓ |

---

## Entity-Relationship Diagram (ERD)

### Database Schema & Relationships

```
                              ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
                              ┃          USERS             ┃
                              ┣━━━━━━━━━━━━━━━━━━━━━━━━━━━━┫
                              ┃ PK: user_id (INT)          ┃
                              ┃ username (VARCHAR)         ┃
                              ┃ email (VARCHAR)            ┃
                              ┃ password_hash (VARCHAR)    ┃
                              ┃ fullname (VARCHAR)         ┃
                              ┃ role (VARCHAR)             ┃
                              ┃ department (VARCHAR)       ┃
                              ┃ profile_picture (TEXT)     ┃
                              ┃ created_at (TIMESTAMP)     ┃
                              ┗━━━┬━━━━━━━━━━━━━━━━━━━━━━━┛
                                  │
                    ┌─────────────┼─────────────┬──────────┐
                    │             │             │          │
           ┌────────▼──────┐ ┌────▼─────────────▼──┐ ┌────▼──────────┐
           ┃  DOCUMENTS    ┃ ┃ POSTS/FEEDBACK    ┃ ┃ NOTIFICATIONS ┃
           ┣────────────────┫ ┣─────────────────────┫ ┣───────────────┫
           ┃ PK: doc_id    ┃ ┃ PK: post_id       ┃ ┃ PK: notif_id  ┃
           ┃ FK: user_id ──┼─┫ FK: user_id ──────┼─┫ FK: user_id   ┃
           ┃ title (VAR)   ┃ ┃ content (TEXT)    ┃ ┃ message (TEXT)┃
           ┃ content (TEXT)┃ ┃ is_approved (BOOL)┃ ┃ type (VARCHAR)┃
           ┃ category (VAR)┃ ┃ created_at (TIME) ┃ ┃ is_read (BOOL)┃
           ┃ status (VAR)  ┃ ┗───────────────────┛ ┃ created_at    ┃
           ┃ uploaded_by   ┃                       ┗───────────────┛
           ┃ created_at    ┃
           ┃ updated_at    ┃
           ┗────────┬───────┘
                    │
        ┌───────────┴──────────┬──────────┐
        │                      │          │
   ┌────▼────────────┐ ┌───────▼──────┐ ┌▼──────────────────┐
   ┃ ANNOUNCEMENTS   ┃ ┃ AUDIT_LOGS   ┃ ┃ USER_LOGS         ┃
   ┣─────────────────┫ ┣──────────────┫ ┣──────────────────┫
   ┃ PK: announce_id┃ ┃ PK: log_id   ┃ ┃ PK: user_log_id  ┃
   ┃ FK: user_id ──┼─┫ FK: admin_id ┃ ┃ FK: user_id ────┼─┫
   ┃ title (VARCHAR)┃ ┃ action (VAR) ┃ ┃ action (VARCHAR) ┃
   ┃ content (TEXT) ┃ ┃ entity_type  ┃ ┃ login_time       ┃
   ┃ target_role    ┃ ┃ entity_id    ┃ ┃ logout_time      ┃
   ┃ scheduled_at   ┃ ┃ details (JAX)┃ ┃ ip_address       ┃
   ┃ expires_at     ┃ ┃ created_at   ┃ ┃ user_agent       ┃
   ┃ is_active      ┃ ┗──────────────┘ ┗──────────────────┘
   ┃ created_at     ┃
   ┗────────────────┘

Legend:
  PK = Primary Key
  FK = Foreign Key
  — = One-to-Many relationship
  ┃ = Table boundary
```

### Relationship Summary

| Entity 1 | Relationship | Entity 2 | Description |
|----------|-------------|----------|-------------|
| USERS | 1:N | DOCUMENTS | One user uploads many documents |
| USERS | 1:N | POSTS | One user creates many posts/feedback |
| USERS | 1:N | NOTIFICATIONS | One user receives many notifications |
| USERS | 1:N | ANNOUNCEMENTS | One user creates many announcements |
| USERS | 1:N | AUDIT_LOGS | Admin logs track user actions |
| USERS | 1:N | USER_LOGS | Track user login/logout history |
| DOCUMENTS | 1:N | POSTS | Users comment on documents |

---

## Product Backlog

### Epic 1: Core Features - COMPLETED ✅
- [x] User Authentication (Login/Register/Logout)
- [x] Dashboard with Statistics
- [x] Document Management (CRUD)
- [x] User Management
- [x] Audit Logging System
- [x] Announcement System
- [x] Notification System
- [x] Profile Management with Picture Upload

---

### Epic 2: Consultation Post Management - IN PROGRESS 🔄

#### Story 1: Advanced Post Features
- [ ] Post Versioning
  - Track post edits over time
  - Allow rollback to previous versions
  - Display edit history
  - Priority: Medium
  - Estimated: 8 story points

- [ ] Bulk Post Operations
  - Multi-select posts
  - Bulk approve/reject functionality
  - Bulk archive with confirmation
  - Bulk export (PDF/CSV)
  - Priority: Medium
  - Estimated: 8 story points

- [ ] Post Comments & Threads
  - Comments on citizen posts
  - Staff/manager replies
  - Mention users with @mention
  - Email notifications for replies
  - Priority: High
  - Estimated: 13 story points

- [ ] Post Moderation Tools
  - Flag inappropriate content
  - Automatic spam detection
  - Content filtering rules
  - Priority: High
  - Estimated: 13 story points

- [ ] Post Workflows
  - Moderation approval workflow
  - Multi-level review process
  - Status tracking (submitted, approved, published, archived)
  - Deadline management
  - Priority: High
  - Estimated: 13 story points

---

### Epic 3: User Management & Roles - IN PROGRESS 🔄

#### Story 1: Enhanced Role-Based Access Control (RBAC)
- [ ] Custom Role Creation
  - Define custom roles (Citizen, Moderator, Manager, Admin)
  - Role permission matrix
  - Role templates
  - Priority: High
  - Estimated: 13 story points

- [ ] Fine-Grained Permissions
  - Post-level permissions
  - Category-level access control
  - Staff viewing restrictions
  - Priority: High
  - Estimated: 13 story points

- [ ] User Groups/Teams
  - Create moderation teams
  - Assign team permissions
  - Team-based notifications
  - Team collaboration features
  - Priority: Medium
  - Estimated: 13 story points

- [ ] User Activity Tracking
  - Track user logins (already partially done)
  - Track post submissions
  - Track searches
  - User behavior analytics
  - Priority: Medium
  - Estimated: 8 story points

---

### Epic 4: Search & Filtering Enhancements - PLANNED 📋

#### Story 1: Advanced Search
- [ ] Full-Text Search
  - Search across all posts and content
  - Search result highlighting
  - Search suggestions/autocomplete
  - Priority: High
  - Estimated: 13 story points

- [ ] Saved Searches
  - Allow users to save search filters
  - Quick access to saved searches
  - Shared saved searches
  - Priority: Low
  - Estimated: 5 story points

- [ ] Advanced Filters
  - Filter by category/topic
  - Filter by status (submitted, approved, rejected)
  - Filter by date range
  - Filter by sentiment
  - Filter by author
  - Combined filters with AND/OR logic
  - Priority: High
  - Estimated: 8 story points

- [ ] Search Analytics
  - Track popular search terms
  - Search trend analysis
  - Search-based topic identification
  - Priority: Low
  - Estimated: 8 story points

---

### Epic 5: Reports & Analytics - IN PROGRESS 🔄

#### Story 1: Enhanced Reporting
- [ ] Custom Report Builder
  - Drag-and-drop report builder
  - Multiple chart types
  - Export reports (PDF, Excel, CSV)
  - Schedule automated reports
  - Priority: High
  - Estimated: 21 story points

- [ ] Dashboard Customization
  - Allow users to customize dashboard widgets
  - Save custom dashboard layouts
  - Multiple dashboard templates
  - Priority: Medium
  - Estimated: 13 story points

- [ ] Performance Metrics
  - System uptime/availability
  - Response time metrics
  - User engagement metrics
  - Post submission/approval metrics
  - Priority: Medium
  - Estimated: 13 story points

- [ ] Export Functionality
  - Export consultation summary reports to PDF
  - Export to Excel
  - Export to CSV
  - Email report delivery
  - Priority: High
  - Estimated: 8 story points

---

### Epic 6: Communication & Notifications - IN PROGRESS 🔄

#### Story 1: Enhanced Notification System
- [ ] Email Notifications
  - Post submission notifications
  - Approval/rejection notifications
  - Response notifications
  - User mention notifications
  - Priority: High
  - Estimated: 8 story points

- [ ] In-App Notifications
  - Real-time notifications (currently exists, enhance)
  - Notification center/inbox
  - Notification preferences
  - Read/unread status
  - Priority: Medium
  - Estimated: 8 story points

- [ ] SMS Notifications
  - SMS for critical alerts
  - SMS for post responses
  - Two-factor authentication via SMS
  - Priority: Low
  - Estimated: 13 story points

- [ ] Push Notifications
  - Browser push notifications
  - Mobile app push notifications
  - Push notification preferences
  - Priority: Medium
  - Estimated: 8 story points

---

### Epic 7: Announcement & Updates - IN PROGRESS 🔄

#### Story 1: Enhanced Announcement System
- [ ] Scheduled Announcements
  - Schedule announcements for future dates
  - Recurring announcements
  - Announcement expiration
  - Priority: Medium
  - Estimated: 8 story points

- [ ] Rich Text Editor
  - WYSIWYG editor for announcements
  - Image/media embedding
  - Code syntax highlighting
  - Priority: Medium
  - Estimated: 5 story points

- [ ] Announcement Targeting
  - Target announcements to specific roles
  - Target by department
  - Target by user groups
  - Priority: Medium
  - Estimated: 8 story points

- [ ] Announcement Analytics
  - Track announcement views
  - Track clicks/engagement
  - Announcement effectiveness metrics
  - Priority: Low
  - Estimated: 8 story points

---

### Epic 8: Audit & Compliance - COMPLETED ✅

- [x] Audit Log System (Implemented)
- [x] Activity Tracking
- [x] Admin Action Logging
- [ ] Compliance Reports
  - Generate GDPR compliance reports
  - Data retention reports
  - User access audit reports
  - Priority: Medium
  - Estimated: 13 story points

- [ ] Data Export & Deletion
  - Allow users to export their data
  - GDPR right to be forgotten
  - Data deletion workflows
  - Priority: High
  - Estimated: 13 story points

---

### Epic 9: User Experience & Interface - IN PROGRESS 🔄

#### Story 1: UI/UX Improvements
- [ ] Mobile App
  - Native mobile app (iOS/Android)
  - Responsive design enhancements
  - Mobile-specific features
  - Priority: High
  - Estimated: 55+ story points

- [ ] Dark Mode
  - Complete dark mode theme (partially exists)
  - User preference saving
  - System-wide dark mode toggle
  - Priority: Low
  - Estimated: 5 story points

- [ ] Accessibility Improvements
  - WCAG 2.1 AA compliance
  - Screen reader optimization
  - Keyboard navigation
  - Color contrast adjustments
  - Priority: High
  - Estimated: 13 story points

- [ ] Performance Optimization
  - Page load time optimization
  - Image optimization
  - Caching strategies
  - Priority: High
  - Estimated: 13 story points

- [ ] UI Polish
  - Consistent icon usage
  - Animation refinements
  - Toast notifications enhancement
  - Modal dialog improvements
  - Priority: Medium
  - Estimated: 8 story points

---

### Epic 10: Integration & API - PLANNED 📋

#### Story 1: API Development
- [ ] REST API
  - Create comprehensive REST API
  - API documentation
  - Rate limiting
  - API versioning
  - Priority: Medium
  - Estimated: 34 story points

- [ ] Third-Party Integrations
  - Email service integration (SendGrid, AWS SES)
  - SMS service integration (Twilio)
  - Cloud storage integration (Google Drive, OneDrive)
  - Single Sign-On (Google, Microsoft)
  - Priority: Low
  - Estimated: 21 story points

- [ ] Webhook Support
  - Outgoing webhooks for document events
  - Webhook management interface
  - Webhook testing tool
  - Priority: Medium
  - Estimated: 8 story points

---

### Epic 11: Citizen Portal Enhancements - IN PROGRESS 🔄

#### Story 1: Portal Features
- [ ] Public Consultation Features
  - Submit feedback/comments on consultations
  - Opinion survey system
  - Sentiment analysis for posts
  - Priority: High
  - Estimated: 13 story points

- [ ] Knowledge Base
  - FAQ section for consultations
  - How-to guides for citizens
  - Video tutorials
  - User documentation
  - Priority: Medium
  - Estimated: 13 story points

- [ ] Consultation Tracking
  - Track consultation progress for citizens
  - Timeline view of consultation phases
  - Notifications for phase changes
  - Priority: Medium
  - Estimated: 8 story points

- [ ] User Profiles Enhancement
  - User submission history
  - Citizen achievement badges
  - Citizen reputation system
  - Priority: Low
  - Estimated: 8 story points

---

## Sprint Priorities

### Current Sprint (Next 2 Weeks)
1. **Email Notification System** (High Priority, 8 points)
   - Implement email notifications for key events
   - Set up email service provider
   
2. **Document Workflows** (High Priority, 21 points)
   - Define multi-level approval workflows
   - Implement workflow status tracking

3. **Custom RBAC** (High Priority, 13 points)
   - Allow creation of custom roles
   - Implement permission matrix

### Next Sprint (2-4 Weeks)
1. **Bulk Document Operations** (Medium Priority, 8 points)
2. **Advanced Filtering** (High Priority, 8 points)
3. **Accessibility Improvements** (High Priority, 13 points)

### Future Sprints (4+ Weeks)
1. **Mobile App Development**
2. **REST API Development**
3. **Document Versioning**
4. **Custom Report Builder**

---

## Technical Debt

### Priority: HIGH 🔴

1. **Database Optimization**
   - Create indexes on frequently queried columns
   - Optimize audit_logs table for large datasets
   - Implement database partitioning
   - Estimated effort: 13 story points

2. **Code Refactoring**
   - Extract repeated code into reusable functions
   - Separate concerns in script.js
   - Create utility functions library
   - Estimated effort: 13 story points

3. **Security Hardening**
   - Implement CSRF protection tokens
   - Add rate limiting on API endpoints
   - Implement password hashing best practices
   - Estimated effort: 8 story points

4. **Error Handling**
   - Implement comprehensive error handling
   - Add error logging system
   - Create user-friendly error messages
   - Estimated effort: 8 story points

### Priority: MEDIUM 🟡

5. **Session Management**
   - Implement session timeout
   - Add session security features
   - Implement remember-me functionality
   - Estimated effort: 5 story points

6. **Logging System**
   - Centralized application logging
   - Log levels (debug, info, warning, error)
   - Log rotation and archiving
   - Estimated effort: 8 story points

7. **Configuration Management**
   - Move hardcoded values to config file
   - Environment-based configuration
   - Secret management
   - Estimated effort: 5 story points

### Priority: LOW 🟢

8. **Code Documentation**
   - Add JSDoc comments to JavaScript files
   - Add PHP documentation
   - Create architecture documentation
   - Estimated effort: 8 story points

9. **Unit Testing**
   - Write unit tests for PHP functions
   - Write tests for JavaScript functions
   - Set up CI/CD pipeline
   - Estimated effort: 21 story points

---

## Bug Fixes

### Critical 🔴

- [ ] None currently identified

### High Priority 🟠

1. **Login Session Issues**
   - Verify session persistence across page refreshes
   - Fix potential session conflicts
   - Estimated effort: 3 story points

2. **Profile Picture Upload Validation**
   - Enhance file type validation
   - Verify file size limits work correctly
   - Estimated effort: 2 story points

### Medium Priority 🟡

3. **Modal Dialog Closing**
   - Verify all modals close properly
   - Check for modal overlay issues
   - Estimated effort: 2 story points

4. **Search Filter Clearing**
   - Verify all filters clear properly
   - Check for filter state issues
   - Estimated effort: 2 story points

5. **Responsive Design Issues**
   - Test on various screen sizes
   - Fix sidebar collapse issues
   - Estimated effort: 5 story points

### Low Priority 🟢

6. **Tooltip Display Issues**
   - Fix tooltip positioning on small screens
   - Improve tooltip styling
   - Estimated effort: 2 story points

---

## Performance Improvements

### High Priority 🔴

1. **Database Query Optimization**
   - Reduce N+1 queries
   - Implement query caching
   - Use database indexes effectively
   - Estimated effort: 13 story points

2. **Asset Optimization**
   - Minify CSS and JavaScript
   - Optimize image sizes
   - Implement lazy loading for images
   - Estimated effort: 8 story points

3. **Caching Strategy**
   - Implement browser caching
   - Server-side caching for frequently accessed data
   - Cache busting strategy
   - Estimated effort: 13 story points

### Medium Priority 🟡

4. **API Response Time**
   - Profile API endpoints
   - Optimize slow queries
   - Implement pagination for large datasets
   - Estimated effort: 8 story points

5. **Frontend Performance**
   - Code splitting for large JavaScript files
   - Reduce JavaScript bundle size
   - Defer non-critical JavaScript
   - Estimated effort: 8 story points

### Low Priority 🟢

6. **Monitoring & Analytics**
   - Implement performance monitoring
   - Real User Monitoring (RUM)
   - Performance dashboards
   - Estimated effort: 13 story points

---

## Story Point Scale

- 1: Trivial (< 1 hour)
- 2: Very Small (1-2 hours)
- 3: Small (2-4 hours)
- 5: Medium (4-8 hours)
- 8: Large (1-2 days)
- 13: Very Large (2-3 days)
- 21: Epic (3-5 days)
- 34: Major Epic (5+ days)
- 55+: Major Project (requires decomposition)

---

## Legend

- ✅ COMPLETED - Fully implemented and tested
- 🔄 IN PROGRESS - Currently being worked on
- 📋 PLANNED - Scheduled for upcoming sprints
- 🔴 HIGH PRIORITY - Critical for system functionality
- 🟡 MEDIUM PRIORITY - Important but not blocking
- 🟢 LOW PRIORITY - Nice to have
- 🔴 CRITICAL BUG - System-breaking issue
- 🟠 HIGH PRIORITY BUG - Significant functionality issue
- 🟡 MEDIUM PRIORITY BUG - Minor functionality issue
- 🟢 LOW PRIORITY BUG - Cosmetic issue

---

## Notes

- **Total Backlog Estimate**: 450+ story points
- **Current Implementation Status**: ~40% complete
- **Estimated Timeline**: 6-9 months for full feature set at 1 sprint/week
- **Team Recommendation**: Prioritize authentication enhancements and core features before expanding to advanced features

---

**Next Steps**: Review this backlog with your team, adjust priorities based on business needs, and begin planning the first sprint.
