# Flow มาตรฐานของ Krua Thai (to-be)

แผนภาพชุดนี้แสดง **ระบบที่ควรเป็น** ตามโมเดลธุรกิจที่ตกลงกันแล้ว: meal subscription ใน Orange County, CA ราคาเป็น USD และตัดเงินรายสัปดาห์ผ่าน Stripe Billing ไม่ใช่สิ่งที่โค้ดทำอยู่ตอนนี้ ส่วนระบบเดิม (as-is) ดูได้ที่ [SYSTEM-MAP.md](SYSTEM-MAP.md) และความหมายของศัพท์อยู่ใน [GLOSSARY.md](../GLOSSARY.md)

ใต้แต่ละแผนภาพมีหัวข้อ **"ต่างจากโค้ดเดิม"** บอกว่าต้องสร้างหรือแก้อะไร

**ฉบับ draw.io:** [diagrams/krua-thai-flows.drawio](diagrams/krua-thai-flows.drawio) มี 7 หน้า (เปิดด้วย draw.io desktop หรือ app.diagrams.net) และมีภาพ PNG ของแต่ละหน้าอยู่ใน [diagrams/png/](diagrams/png/) ถ้าแก้แผนภาพ ให้แก้ทั้งไฟล์ draw.io และ mermaid ในหน้านี้ให้ตรงกัน

| หน้า | ภาพ |
|---|---|
| 1. System Context | [1-system-context.png](diagrams/png/1-system-context.png) |
| 2. รอบรายสัปดาห์ (Swimlane) | [2-weekly-cycle.png](diagrams/png/2-weekly-cycle.png) |
| 3. State: Subscription | [3-subscription-state.png](diagrams/png/3-subscription-state.png) |
| 4. State: Order | [4-order-state.png](diagrams/png/4-order-state.png) |
| 5. Sequence: สมัคร + Stripe | [5-seq-signup.png](diagrams/png/5-seq-signup.png) |
| 6. Sequence: ตัดเงินรายสัปดาห์ | [6-seq-weekly-charge.png](diagrams/png/6-seq-weekly-charge.png) |
| 7. ER (ฉบับที่เสนอ) | [7-er-proposed.png](diagrams/png/7-er-proposed.png) |
| 8. System Flow | [8-system-flow.png](diagrams/png/8-system-flow.png) |

## สารบัญ

1. [System Context](#1-system-context)
2. [Swimlane: รอบรายสัปดาห์](#2-swimlane-รอบรายสัปดาห์)
3. [State Machine: Subscription](#3-state-machine-subscription)
4. [State Machine: Order](#4-state-machine-order)
5. [Sequence: สมัครและตัดเงินผ่าน Stripe](#5-sequence-สมัครและตัดเงินผ่าน-stripe)
6. [ER Diagram (ฉบับที่เสนอ)](#6-er-diagram-ฉบับที่เสนอ)
7. [System Flow](#7-system-flow)

---

## 1. System Context

ระบบคุยกับใครและกับระบบภายนอกอะไรบ้าง

```mermaid
flowchart LR
    C([Customer]) -->|สมัคร, เลือกเมนู, Skip/Pause| KT
    A([Admin]) -->|จัดการ Plan, Weekly Menu, มอบงาน| KT
    K([Kitchen Staff]) -->|กด Preparing / Ready| KT
    R([Rider]) -->|ส่งของ, Proof of Delivery| KT

    KT[[Krua Thai<br/>PHP + MySQL]]

    KT <-->|Checkout, Subscription,<br/>Invoice, webhook| S[(Stripe Billing)]
    KT -->|จัดเส้นทาง| G[(Google Route<br/>Optimization)]
    KT -->|อีเมลยืนยัน, แจ้งเตือน| E[(ผู้ให้บริการอีเมล)]
    KT -->|เก็บรูป Proof of Delivery| F[(ที่เก็บไฟล์<br/>นอก web root)]
```

**ต่างจากโค้ดเดิม**
- ต้องเพิ่ม **Stripe** เพราะตอนนี้ไม่มี payment gateway เลย
- เปลี่ยนจาก Gmail ส่วนตัวไปใช้ผู้ให้บริการอีเมลธุรกรรมบนโดเมนของร้าน
- ย้ายที่เก็บรูปออกจาก web root เพื่อไม่ให้ไฟล์ที่อัปโหลดถูกรันเป็นโค้ดได้
- ตัด social login ออกจากแผนภาพไว้ก่อน เพราะตอนนี้ใช้ไม่ได้ ค่อยเพิ่มกลับเมื่อพร้อม

---

## 2. Swimlane: รอบรายสัปดาห์

หัวใจของธุรกิจ คือรอบที่วนทุกสัปดาห์ แต่ละ lane คือคนหรือระบบที่รับผิดชอบขั้นนั้น

```mermaid
flowchart TB
    subgraph AD[Admin]
        A1[เปิด Weekly Menu<br/>ของสัปดาห์ถัดไป]
        A6[มอบงานให้ Rider<br/>อัตโนมัติหรือ manual]
    end

    subgraph CU[Customer]
        C1{เลือกเมนูหรือ Skip<br/>ก่อน Cutoff?}
        C2[เลือก Meal ตามจำนวนใน Plan<br/>แบ่งตาม Delivery Day]
        C3[Skip สัปดาห์นี้]
        C9[อัปเดตบัตร]
    end

    subgraph SY[ระบบ ตอน Cutoff]
        S1[ไม่ได้เลือก: ระบบเลือก Meal ให้]
        S2[Weekly Charge ผ่าน Stripe]
        S3{ตัดเงินผ่าน?}
        S4[สร้าง Order<br/>1 ใบต่อ Delivery Day]
        S5[แจ้ง Customer<br/>Subscription เป็น Past Due]
        S6[ไม่สร้าง Order<br/>ไม่ตัดเงิน]
    end

    subgraph KI[Kitchen Staff]
        K1[ดูรายการที่ต้องทำ<br/>ของ Delivery Day นั้น]
        K2[Preparing → Ready]
    end

    subgraph RI[Rider]
        R1[รับของ: Out for Delivery]
        R2{ส่งถึง?}
        R3[Delivered + Proof of Delivery]
        R4[Failed + เหตุผล]
    end

    A1 --> C1
    C1 -->|เลือกเอง| C2 --> S2
    C1 -->|Skip| C3 --> S6
    C1 -->|ไม่ทำอะไร| S1 --> S2
    S2 --> S3
    S3 -->|ผ่าน| S4 --> K1 --> K2 --> A6 --> R1 --> R2
    S3 -->|ไม่ผ่าน| S5 --> C9 --> S2
    R2 -->|ส่งถึง| R3
    R2 -->|ส่งไม่ได้| R4
```

**ต่างจากโค้ดเดิม**
- **ยังไม่มีงานที่รันตอน Cutoff เลย** (ไม่มี cron) ต้องสร้างงานที่เลือกเมนูให้, ตัดเงิน และสร้าง Order
- ตอนนี้ไม่มีการสร้าง Order ต่อ Delivery Day ระบบใช้ Subscription แทนทั้งหมด
- ตอนนี้ครัวทำได้แค่ดู และ Rider เป็นคนกดสถานะ "กำลังทำในครัว" ต้องย้ายสิทธิ์กดสถานะ Preparing / Ready มาให้ครัว
- ต้องมีทางมอบงานแบบ manual ด้วย เพราะตอนนี้ถ้าไม่มี key ของ Google ก็มอบงานไม่ได้เลย
- **Timezone ต้องเป็น `America/Los_Angeles`** ทั้งระบบ ไม่อย่างนั้นเวลา Cutoff จะคลาด

---

## 3. State Machine: Subscription

Subscription คือ**สัญญา** ส่วน Skip ไม่ใช่สถานะ เพราะเป็นการข้ามแค่สัปดาห์เดียว และ Subscription ยัง Active อยู่

```mermaid
stateDiagram-v2
    [*] --> PendingPayment: Customer เลือก Plan + Delivery Day
    PendingPayment --> Active: Stripe ยืนยันการจ่ายเงินแรก (webhook)
    PendingPayment --> [*]: ไม่จ่ายภายในเวลาที่กำหนด

    Active --> Paused: Customer กด Pause
    Paused --> Active: Customer กด Resume

    Active --> PastDue: Weekly Charge ไม่ผ่าน
    PastDue --> Active: อัปเดตบัตร / retry ผ่าน
    PastDue --> Paused: retry ไม่ผ่านจนครบ

    Active --> Cancelled: Customer หรือ Admin กด Cancel
    Paused --> Cancelled: Customer หรือ Admin กด Cancel
    PastDue --> Cancelled: Admin กด Cancel
    Cancelled --> [*]
```

| สถานะ | ตัดเงินไหม | สร้าง Order ไหม |
|---|---|---|
| PendingPayment | ยัง | ไม่ |
| Active | ทุกสัปดาห์ที่ไม่ได้ Skip | ใช่ |
| PastDue | กำลัง retry | ไม่ |
| Paused | ไม่ | ไม่ |
| Cancelled | ไม่ (สิ้นสุดถาวร) | ไม่ |

**ต่างจากโค้ดเดิม**
- ตอนนี้ Subscription เป็น `active` ทันทีโดยไม่มีการจ่ายเงินจริง
- ตอนนี้กด `renew` ได้ ซึ่งทำให้ Subscription ที่ Cancel แล้วกลับมา active **ต้องตัดออก** เพราะ Cancel คือจบถาวร ถ้าจะกลับมาต้องสมัครใหม่
- ไม่มีสถานะ PastDue
- ต้องเลิกเขียนค่า `delivered` ลงใน `subscriptions.status` เพราะค่านี้เป็นของ Order

---

## 4. State Machine: Order

Order คือ**การส่ง 1 ครั้งต่อ 1 Delivery Day** แต่ละขั้นมีคนรับผิดชอบชัดเจน

```mermaid
stateDiagram-v2
    [*] --> Scheduled: ระบบสร้างหลัง Weekly Charge ผ่าน

    Scheduled --> Preparing: Kitchen Staff เริ่มทำ
    Preparing --> Ready: Kitchen Staff แพ็กเสร็จ
    Ready --> OutForDelivery: Rider รับของ (เฉพาะงานของวันนั้น)

    OutForDelivery --> Delivered: Rider ส่งถึง + Proof of Delivery (บังคับ)
    OutForDelivery --> Failed: Rider ส่งไม่ได้ + เหตุผล

    Scheduled --> Cancelled: Admin ยกเลิก (คืนเงิน)
    Failed --> [*]: Admin ตัดสินว่าจะส่งใหม่หรือคืนเงิน
    Delivered --> [*]
    Cancelled --> [*]
```

| ขั้น | คนที่กด | เงื่อนไข |
|---|---|---|
| Scheduled | ระบบ | หลัง Cutoff และ Weekly Charge ผ่าน |
| Preparing, Ready | **Kitchen Staff** | เฉพาะ Order ของ Delivery Day นั้น |
| Out for Delivery, Delivered, Failed | **Rider** ที่ได้รับมอบงาน | ปิดงานของวันอื่นไม่ได้ และ Delivered ต้องมีรูป |
| Cancelled | Admin | ก่อนเริ่มทำอาหาร |

**ต่างจากโค้ดเดิม**
- ตอนนี้ไม่มี Order แยกจาก Subscription เลย
- Rider กดได้ทุกสถานะ และกดงานของวันอื่นได้ ต้องจำกัดตามตารางด้านบน
- ไม่มีสถานะ Failed
- รูปหลักฐานตอนนี้ไม่บังคับ และช่องอัปโหลดยังไม่ปลอดภัย

---

## 5. Sequence: สมัครและตัดเงินผ่าน Stripe

แนวทางคือ **server ของเราไม่แตะข้อมูลบัตรเลย** และถือว่า webhook ของ Stripe เป็นความจริงเรื่องการจ่ายเงิน ห้ามเชื่อหน้า redirect

### 5.1 สมัคร Subscription

```mermaid
sequenceDiagram
    autonumber
    actor C as Customer
    participant W as Krua Thai (PHP)
    participant DB as MySQL
    participant S as Stripe

    C->>W: เลือก Plan + Delivery Day
    W->>W: เช็ก ZIP อยู่ใน Delivery Zone
    W->>DB: สร้าง subscription (PendingPayment)
    W->>S: สร้าง Checkout Session (mode=subscription, ราคา weekly ของ Plan)
    S-->>W: URL ของ Checkout
    W-->>C: redirect ไปหน้า Stripe
    C->>S: กรอกบัตร / Apple Pay / Google Pay
    S-->>C: redirect กลับหน้า "กำลังยืนยัน"
    S->>W: webhook checkout.session.completed (มีลายเซ็น)
    W->>W: ตรวจลายเซ็น webhook
    W->>DB: subscription = Active, เก็บ stripe_subscription_id
    W-->>C: อีเมลยืนยันการสมัคร
```

### 5.2 ตัดเงินรายสัปดาห์ (ตอน Cutoff)

```mermaid
sequenceDiagram
    autonumber
    participant J as งานตอน Cutoff (cron)
    participant W as Krua Thai (PHP)
    participant DB as MySQL
    participant S as Stripe
    actor C as Customer

    J->>DB: ดึง Subscription ที่ Active และไม่ได้ Skip
    J->>DB: ใครยังไม่เลือกเมนู ระบบเลือก Meal ให้
    S->>S: ถึงรอบบิลรายสัปดาห์ สร้าง Invoice แล้วตัดบัตร
    alt ตัดเงินผ่าน
        S->>W: webhook invoice.paid
        W->>DB: บันทึก Weekly Charge = paid
        W->>DB: สร้าง Order (Scheduled) 1 ใบต่อ Delivery Day
        W-->>C: อีเมลสรุปเมนูของสัปดาห์
    else ตัดเงินไม่ผ่าน
        S->>W: webhook invoice.payment_failed
        W->>DB: Weekly Charge = failed, subscription = PastDue
        W-->>C: แจ้งให้อัปเดตบัตร
        S->>S: Smart Retries ตามค่าที่ตั้งใน Stripe
    end
```

**ข้อควรตัดสินใจตอนออกแบบจริง**
- **ตั้งรอบบิลของ Stripe ให้ตรงกับ Cutoff** (billing cycle anchor) เพื่อให้ Invoice ออกหลังเลือกเมนูเสร็จ
- **Skip:** ใช้ `pause_collection` ของ Stripe เฉพาะรอบนั้น หรือออก credit คืน
- **Pause:** ใช้ `pause_collection` แบบไม่มีกำหนด / **Cancel:** ยกเลิก subscription ใน Stripe
- webhook ต้องจัดการได้แม้ถูกส่งซ้ำ (idempotent) โดยใช้ event id ของ Stripe กันการทำซ้ำ

**ต่างจากโค้ดเดิม**
- ตอนนี้ `checkout.php` บันทึกว่าจ่ายเงินแล้วทันที โดยไม่มีการเก็บเงินจริง
- ราคาต้องมาจาก Price ของ Stripe ที่ผูกกับ Plan เท่านั้น ห้ามมี plan สำรองหรือการแปลงหน่วยราคาแบบเดา

---

## 6. ER Diagram (ฉบับที่เสนอ)

แยก **Subscription (สัญญา)** ออกจาก **Order (การส่ง)** และเพิ่มตารางของรอบรายสัปดาห์ ชื่อตารางใช้ศัพท์จาก GLOSSARY แต่ละตารางแสดงเฉพาะคอลัมน์หลัก

```mermaid
erDiagram
    users ||--o{ subscriptions : "มี"
    plans ||--o{ subscriptions : "กำหนด"
    delivery_zones ||--o{ users : "ZIP อยู่ใน"

    subscriptions ||--o{ weekly_selections : "เลือกเมนูรายสัปดาห์"
    subscriptions ||--o{ weekly_charges : "ถูกตัดเงิน"
    subscriptions ||--o{ orders : "สร้าง"

    weekly_menus ||--o{ weekly_menu_meals : "ประกอบด้วย"
    meals ||--o{ weekly_menu_meals : "อยู่ใน"
    meals ||--o{ weekly_selections : "ถูกเลือก"

    weekly_charges ||--o{ orders : "จ่ายค่า"
    orders ||--o{ order_items : "มี"
    meals ||--o{ order_items : "เป็น"
    users ||--o{ orders : "Rider ได้รับมอบ"
    orders ||--o| delivery_proofs : "หลักฐาน"

    users {
        char36 id PK
        string email
        string role "customer | admin | kitchen | rider"
        string zip_code
    }
    plans {
        char36 id PK
        int meals_per_week
        decimal weekly_price
        string stripe_price_id
        bool is_active
    }
    subscriptions {
        char36 id PK
        char36 user_id FK
        char36 plan_id FK
        string status "pending_payment | active | past_due | paused | cancelled"
        string delivery_days "wed, sat"
        string stripe_subscription_id
    }
    weekly_menus {
        char36 id PK
        date week_start
        datetime cutoff_at
    }
    meals {
        char36 id PK
        string name
        bool is_available
    }
    weekly_menu_meals {
        char36 weekly_menu_id FK
        char36 meal_id FK
    }
    weekly_selections {
        char36 id PK
        char36 subscription_id FK
        date week_start
        date delivery_date
        char36 meal_id FK
        int quantity
        bool auto_selected
        bool skipped
    }
    weekly_charges {
        char36 id PK
        char36 subscription_id FK
        date week_start
        decimal amount
        string status "pending | paid | failed | refunded"
        string stripe_invoice_id
    }
    orders {
        char36 id PK
        char36 subscription_id FK
        char36 weekly_charge_id FK
        date delivery_date
        string status "scheduled | preparing | ready | out_for_delivery | delivered | failed | cancelled"
        char36 rider_id FK
    }
    order_items {
        char36 order_id FK
        char36 meal_id FK
        int quantity
    }
    delivery_proofs {
        char36 order_id FK
        string photo_path
        datetime delivered_at
    }
    delivery_zones {
        string zip_code PK
        bool is_active
    }
```

**ต่างจาก schema เดิม**
- **เพิ่มตาราง** `weekly_menus`, `weekly_menu_meals`, `weekly_selections`, `weekly_charges` และ `delivery_proofs`
- `orders` กลับมาเป็นตารางหลักของการส่ง โดยผูกกับ `weekly_charges`
- `menus` เปลี่ยนชื่อเป็น `meals` ให้ตรงกับศัพท์
- `subscription_plans` เหลือแค่ Plan ที่ไม่มีระยะเวลาสิ้นสุด ไม่ใช้ `weeks_duration` แล้ว
- `delivery_zones` ใช้บล็อก ZIP จริง แทนรายการที่ hardcode อยู่ในโค้ด
- `subscription_menus`, `payments` และตาราง log ของการจ่ายเงินแบบเดิม ให้ย้ายข้อมูลแล้วเลิกใช้
- `role` เหลือ 4 ค่า ตัด `support` ออก

---

## 7. System Flow

ภาพรวมการทำงานของทั้งระบบ แบ่งเป็น 5 ชั้นจากซ้ายไปขวา: ผู้ใช้ → หน้าจอ → Auth/Role guard → service → ข้อมูลและระบบภายนอก ทุก request ต้องผ่าน guard กลางก่อนถึง service

```mermaid
flowchart LR
    subgraph USERS[ผู้ใช้]
        AD([Admin])
        CU([Customer])
        KS([Kitchen Staff])
        RD([Rider])
    end

    subgraph KT[Krua Thai - PHP + MySQL]
        subgraph UI[หน้าจอ]
            UCON[Admin Console]
            UPOR[Customer Portal]
            UKIT[Kitchen View]
            URID[Rider App]
        end
        GUARD{{Auth · Session · Role guard}}
        subgraph SVC[Service]
            SCAT[Catalog Service]
            SSUB[Subscription Service]
            SJOB[Weekly Cycle Job - cron ตอน Cutoff]
            SWHK[Stripe Webhook Handler]
            SORD[Order Service]
            SDLV[Delivery Service]
            SNTF[Notification Service]
        end
        subgraph DATA[ข้อมูล MySQL]
            DCAT[(plans · weekly_menus · meals)]
            DSUB[(users · delivery_zones · subscriptions · weekly_selections)]
            DCHG[(weekly_charges)]
            DORD[(orders · order_items · delivery_proofs)]
        end
    end

    subgraph EXT[ระบบภายนอก]
        XSTR[Stripe Billing]
        XGGL[Google Route Optimization]
        XFIL[ที่เก็บไฟล์ นอก web root]
        XEML[ผู้ให้บริการอีเมล]
    end

    AD --> UCON
    CU --> UPOR
    KS --> UKIT
    RD --> URID
    UCON --> GUARD
    UPOR --> GUARD
    UKIT --> GUARD
    URID --> GUARD
    GUARD --> SCAT
    GUARD --> SSUB
    GUARD --> SORD
    GUARD --> SDLV

    SCAT --> DCAT
    SSUB --> DSUB
    SJOB -->|อ่าน / เขียน| DSUB
    SSUB -->|Checkout · pause · cancel| XSTR
    XSTR -->|webhook| SWHK
    SWHK --> DCHG
    SWHK -->|invoice.paid → สร้าง Order| SORD
    SORD --> DORD
    SDLV --> DORD
    SDLV -->|optimizeTours| XGGL
    SDLV -->|อัปโหลดรูป| XFIL
    SDLV -.->|event| SNTF
    SWHK -.->|event| SNTF
    SNTF --> XEML
```

**ต่างจากโค้ดเดิม**
- **ยังไม่มี guard กลาง** ตอนนี้แต่ละหน้าเขียนการเช็กสิทธิ์เอง และมีหลายหน้าที่ไม่เช็กเลย
- ยังไม่มี Weekly Cycle Job, Stripe Webhook Handler หรือ Notification Service แยกเป็นส่วนของตัวเอง
- โค้ดเดิมเขียนแต่ละหน้าเป็นไฟล์ PHP ที่ทั้งแสดงผลและยิง SQL เองในไฟล์เดียว การแยก service ตามภาพนี้ไม่จำเป็นต้องทำทีเดียว ทยอยแยกตอนแก้แต่ละ flow ได้
