# RishiPath POS - System Flowcharts

> **Visual guide to every business process in the system**

---

## Table of Contents

1. [Complete System Overview](#1-complete-system-overview)
2. [Store Setup Flow](#2-store-setup-flow)
3. [Product Catalog Flow](#3-product-catalog-flow)
4. [Purchase & Receiving Flow](#4-purchase--receiving-flow)
5. [Inventory Management Flow](#5-inventory-management-flow)
6. [Point of Sale (POS) Flow](#6-point-of-sale-pos-flow)
7. [Customer & Loyalty Flow](#7-customer--loyalty-flow)
8. [Supplier & Payment Flow](#8-supplier--payment-flow)
9. [Reporting & Analytics Flow](#9-reporting--analytics-flow)
10. [Multi-Store Operations Flow](#10-multi-store-operations-flow)
11. [B2B / Wholesale Flow](#11-b2b--wholesale-flow)
12. [Complete Business Lifecycle](#12-complete-business-lifecycle)

---

## 1. Complete System Overview

```mermaid
graph TB
    subgraph SETUP["🏗️ SETUP PHASE"]
        ORG[Create Organization] --> STORE[Setup Stores]
        STORE --> USERS[Add Users & Roles]
        USERS --> CAT[Create Categories]
        CAT --> PROD[Add Products]
        PROD --> VAR[Create Variants & Pricing]
        VAR --> SUP[Register Suppliers]
    end

    subgraph OPERATIONS["⚙️ DAILY OPERATIONS"]
        PUR[Purchase Orders] --> REC[Receive Stock]
        REC --> INV[Inventory Updates]
        INV --> POS[POS Billing]
        POS --> SALE[Complete Sale]
        SALE --> RECEIPT[Print/WhatsApp Receipt]
    end

    subgraph GROWTH["📈 GROWTH & INSIGHTS"]
        CUST[Customer Management] --> LOYAL[Loyalty Program]
        LOYAL --> REWARD[Rewards & Points]
        REPORT[Reports & Analytics] --> ALERT[Smart Alerts]
        ALERT --> DECIDE[Business Decisions]
    end

    SETUP --> OPERATIONS
    OPERATIONS --> GROWTH
    SALE --> CUST
    SALE --> REPORT

    style SETUP fill:#e8f5e9,stroke:#2e7d32,color:#000
    style OPERATIONS fill:#e3f2fd,stroke:#1565c0,color:#000
    style GROWTH fill:#fff3e0,stroke:#e65100,color:#000
```

---

## 2. Store Setup Flow

> **One-time setup to get your business running on RishiPath**

```mermaid
flowchart TD
    START([Start: New Business]) --> ORG

    subgraph ORG_SETUP["Step 1: Organization"]
        ORG[Create Organization]
        ORG --> ORG_DETAILS["Set Name, Country<br/>Currency (INR/NPR)<br/>Timezone, Locale"]
    end

    subgraph STORE_SETUP["Step 2: Store & Team"]
        S1[Create Store Location] --> S2["Address, Phone<br/>Tax Number, License"]
        S2 --> T1[Create POS Terminal]
        T1 --> U1[Add Users]
        U1 --> R1{Assign Roles}
        R1 --> |Admin| ADMIN["Full Access<br/>70+ Permissions"]
        R1 --> |Manager| MGR["44 Permissions<br/>Reports, Inventory"]
        R1 --> |Cashier| CASH["12 Permissions<br/>POS, Basic Customer"]
        R1 --> |Inventory Clerk| CLERK["19 Permissions<br/>Stock, Purchases"]
    end

    subgraph CATALOG_SETUP["Step 3: Product Catalog"]
        C1[Create Categories] --> C2["e.g., Oils, Powders<br/>Capsules, Special Items"]
        C2 --> P1[Add Products]
        P1 --> P2["Name in 4 Languages<br/>Sanskrit, Hindi, Nepali, English"]
        P2 --> V1[Create Variants]
        V1 --> V2["Pack Size: 100ml, 200ml, 500ml<br/>MRP India / Selling Price Nepal<br/>Cost Price, HSN Code"]
        V2 --> B1[Generate Barcodes]
    end

    subgraph SUPPLIER_SETUP["Step 4: Suppliers"]
        SUP1[Register Suppliers] --> SUP2["Name, Contact<br/>Tax Number, Address"]
    end

    ORG_DETAILS --> S1
    ADMIN & MGR & CASH & CLERK --> C1
    B1 --> SUP1
    SUP1 --> READY([✅ Ready for Business!])

    style ORG_SETUP fill:#e8f5e9,stroke:#2e7d32,color:#000
    style STORE_SETUP fill:#e3f2fd,stroke:#1565c0,color:#000
    style CATALOG_SETUP fill:#fff3e0,stroke:#e65100,color:#000
    style SUPPLIER_SETUP fill:#fce4ec,stroke:#c62828,color:#000
```

---

## 3. Product Catalog Flow

> **How products are structured in the system**

```mermaid
flowchart TD
    subgraph HIERARCHY["Product Hierarchy"]
        ORG[Organization] --> CAT[Category]
        CAT --> |"e.g., Hair Oils"| PROD[Product]
        PROD --> |"e.g., Amla Hair Oil"| VAR1["Variant: 100ml<br/>₹150 India / रू200 Nepal"]
        PROD --> VAR2["Variant: 200ml<br/>₹280 India / रू375 Nepal"]
        PROD --> VAR3["Variant: 500ml<br/>₹650 India / रू870 Nepal"]
    end

    subgraph PRODUCT_DETAILS["Product Information"]
        PROD --> NAMES["🌍 Multi-Language<br/>English: Amla Hair Oil<br/>Sanskrit: आमलकी तैलम्<br/>Hindi: आंवला तेल<br/>Nepali: अमला तेल"]
        PROD --> TYPE["📦 Product Type<br/>Tailam (Oil)<br/>Choorna (Powder)<br/>Ghritam (Ghee)<br/>Capsule / Tea / Honey"]
        PROD --> TRACK["🔍 Tracking<br/>Requires Batch: Yes<br/>Requires Expiry: Yes<br/>Shelf Life: 24 months<br/>Prescription: No"]
    end

    subgraph VARIANT_DETAILS["Each Variant Has"]
        VAR1 --> SKU["SKU: AYU-OIL-AML-100"]
        VAR1 --> BARCODE["Barcode: RSH-42-A7B3"]
        VAR1 --> PRICE["Dynamic Pricing<br/>Store-specific overrides"]
        VAR1 --> STOCK["Stock Levels<br/>Per store tracking"]
        VAR1 --> BATCH["Batches<br/>With expiry dates"]
    end

    style HIERARCHY fill:#e8f5e9,stroke:#2e7d32,color:#000
    style PRODUCT_DETAILS fill:#e3f2fd,stroke:#1565c0,color:#000
    style VARIANT_DETAILS fill:#fff3e0,stroke:#e65100,color:#000
```

---

## 4. Purchase & Receiving Flow

> **From placing an order to stock on the shelf**

```mermaid
flowchart TD
    START([Need to Restock]) --> CREATE

    subgraph PURCHASE["Purchase Order"]
        CREATE[Create Purchase Order] --> DETAILS["Select Supplier<br/>Select Store<br/>Add Items & Quantities<br/>Set Cost Prices"]
        DETAILS --> STATUS{Save As}
        STATUS --> |Save| DRAFT[📋 Draft]
        STATUS --> |Submit| ORDERED[📦 Ordered]
    end

    subgraph RECEIVE["Receiving"]
        ORDERED --> DELIVERY[Supplier Delivers]
        DELIVERY --> CHECK{Verify Items}
        CHECK --> |All Good| RECEIVE_ALL[Mark as Received]
        CHECK --> |Issues| PARTIAL["Partial Receive<br/>Note Discrepancies"]

        RECEIVE_ALL --> AUTO_BATCH["🔄 AUTO: Create Batches<br/>Batch Number<br/>Expiry Date<br/>Purchase Price<br/>Quantity"]
        PARTIAL --> AUTO_BATCH
    end

    subgraph INVENTORY["Inventory Updates"]
        AUTO_BATCH --> STOCK_UP["🔄 AUTO: Stock Level +<br/>Quantity Added to Store"]
        STOCK_UP --> MOVEMENT["🔄 AUTO: Movement Logged<br/>Type: Purchase<br/>User, Date, Quantity"]
        MOVEMENT --> LEDGER["🔄 AUTO: Supplier Ledger<br/>Debit Entry Created<br/>Balance Updated"]
    end

    subgraph PAYMENT["Supplier Payment"]
        LEDGER --> PAY{Pay Supplier}
        PAY --> |Full| PAID["✅ Fully Paid"]
        PAY --> |Partial| PARTIAL_PAY["⏳ Partially Paid"]
        PAY --> |Later| UNPAID["📋 Outstanding"]
    end

    DRAFT --> |Edit & Submit| ORDERED

    style PURCHASE fill:#e3f2fd,stroke:#1565c0,color:#000
    style RECEIVE fill:#e8f5e9,stroke:#2e7d32,color:#000
    style INVENTORY fill:#fff3e0,stroke:#e65100,color:#000
    style PAYMENT fill:#fce4ec,stroke:#c62828,color:#000
```

---

## 5. Inventory Management Flow

> **How stock moves through the system**

```mermaid
flowchart TD
    subgraph STOCK_IN["📥 Stock Comes In"]
        PUR[Purchase Received] --> |"Batch Created"| ADD[Stock +]
        RET[Customer Return] --> |"Return Processed"| ADD
        TRANS_IN[Transfer From<br/>Another Store] --> ADD
        ADJ_UP[Manual Adjustment<br/>Recount: Found Extra] --> ADD
    end

    subgraph TRACKING["📊 Stock Tracking"]
        ADD --> LEVEL["Stock Level<br/>Per Variant, Per Store"]
        LEVEL --> BATCH["Batch Tracking<br/>Batch #, Expiry Date<br/>Qty Remaining"]
        BATCH --> FIFO["FIFO Rule<br/>Oldest Batch Sells First"]
    end

    subgraph STOCK_OUT["📤 Stock Goes Out"]
        FIFO --> SALE[Sold to Customer]
        FIFO --> DAMAGE[Damaged/Expired]
        FIFO --> TRANS_OUT[Transferred to<br/>Another Store]
        FIFO --> ADJ_DOWN[Manual Adjustment<br/>Recount: Missing]
        FIFO --> PUR_RET[Returned to Supplier]
    end

    subgraph ALERTS["🚨 Smart Alerts"]
        LEVEL --> CHECK{Stock Level Check}
        CHECK --> |"Below Reorder Level"| LOW["⚠️ Low Stock Alert"]
        CHECK --> |"Zero"| OUT["🔴 Out of Stock Alert"]
        CHECK --> |"Healthy"| OK["✅ Normal"]

        BATCH --> EXPIRY{Expiry Check}
        EXPIRY --> |"Expired"| EXP_RED["🔴 EXPIRED - Remove"]
        EXPIRY --> |"< 30 Days"| EXP_YELLOW["🟡 Expiring Soon"]
        EXPIRY --> |"> 30 Days"| EXP_GREEN["✅ Safe"]
    end

    subgraph AUDIT["📋 Full Audit Trail"]
        SALE & DAMAGE & TRANS_OUT & ADJ_DOWN & PUR_RET --> LOG["Every Movement Logged<br/>Who, When, Why, How Much<br/>From Qty → To Qty"]
    end

    style STOCK_IN fill:#e8f5e9,stroke:#2e7d32,color:#000
    style TRACKING fill:#e3f2fd,stroke:#1565c0,color:#000
    style STOCK_OUT fill:#fff3e0,stroke:#e65100,color:#000
    style ALERTS fill:#fce4ec,stroke:#c62828,color:#000
    style AUDIT fill:#f3e5f5,stroke:#6a1b9a,color:#000
```

---

## 6. Point of Sale (POS) Flow

> **The moment of truth — selling to customers**

```mermaid
flowchart TD
    START([Customer Arrives]) --> SEARCH

    subgraph CART["🛒 Building the Cart"]
        SEARCH["Search Product<br/>By Name / SKU / Barcode<br/>In Any Language"] --> SELECT[Select Product Variant]
        SELECT --> QTY["Set Quantity<br/>See Live Stock Available"]
        QTY --> |"Stock OK"| ADD[Add to Cart]
        QTY --> |"Insufficient Stock"| WARN["⚠️ Low Stock Warning"]
        ADD --> MORE{More Items?}
        MORE --> |Yes| SEARCH
        MORE --> |No| CUSTOMER
    end

    subgraph CUSTOMER_STEP["👤 Customer"]
        CUSTOMER{Select Customer}
        CUSTOMER --> |Existing| FIND["Search by Name/Phone"]
        CUSTOMER --> |New| CREATE["Quick Create<br/>Name + Phone"]
        CUSTOMER --> |Walk-in| SKIP["Anonymous Sale"]
        FIND & CREATE & SKIP --> LOYALTY_CHECK
    end

    subgraph LOYALTY["🎁 Loyalty & Discounts"]
        LOYALTY_CHECK{Loyalty Member?}
        LOYALTY_CHECK --> |Yes| POINTS["Show Available Points"]
        POINTS --> REDEEM{Redeem Reward?}
        REDEEM --> |Yes| APPLY_REWARD["Apply Reward<br/>Discount / Free Product"]
        REDEEM --> |No| DISCOUNT
        LOYALTY_CHECK --> |No| DISCOUNT
        APPLY_REWARD --> DISCOUNT
        DISCOUNT["Apply Manual Discount<br/>(Optional)"]
    end

    subgraph PAYMENT["💰 Payment"]
        DISCOUNT --> TOTAL["Calculate Total<br/>Subtotal + Tax (GST/VAT)<br/>- Discounts - Rewards"]
        TOTAL --> PAY{Payment Method}
        PAY --> |"💵 Cash"| CASH["Enter Amount<br/>Calculate Change"]
        PAY --> |"📱 UPI"| UPI["Auto-fill Amount<br/>Reference Number"]
        PAY --> |"💳 Card"| CARD["Card Number<br/>Reference"]
        PAY --> |"Split"| SPLIT["Multiple Methods<br/>₹500 Cash + ₹200 UPI"]
    end

    subgraph COMPLETE["✅ Sale Complete"]
        CASH & UPI & CARD & SPLIT --> DONE[Complete Sale]
        DONE --> AUTO1["🔄 AUTO: Receipt Generated<br/>RSH-20260419-001"]
        DONE --> AUTO2["🔄 AUTO: Stock Deducted<br/>FIFO Batch Selection"]
        DONE --> AUTO3["🔄 AUTO: Loyalty Points<br/>Awarded to Customer"]
        DONE --> AUTO4["🔄 AUTO: Customer Stats<br/>Total Spent Updated"]
        AUTO1 --> DELIVER{Deliver Receipt}
        DELIVER --> |Print| PRINT["🖨️ Print Receipt"]
        DELIVER --> |WhatsApp| WA["📱 Send via WhatsApp"]
        DELIVER --> |Both| BOTH["Print + WhatsApp"]
    end

    subgraph PARK["⏸️ Park Sale (Optional)"]
        MORE --> |"Customer Waiting"| PARK_SALE["Park Current Sale"]
        PARK_SALE --> NEW_CUST["Serve Next Customer"]
        NEW_CUST --> RESUME["Resume Parked Sale Later"]
        RESUME --> CUSTOMER
    end

    style CART fill:#e3f2fd,stroke:#1565c0,color:#000
    style CUSTOMER_STEP fill:#e8f5e9,stroke:#2e7d32,color:#000
    style LOYALTY fill:#fff3e0,stroke:#e65100,color:#000
    style PAYMENT fill:#fce4ec,stroke:#c62828,color:#000
    style COMPLETE fill:#f3e5f5,stroke:#6a1b9a,color:#000
    style PARK fill:#fff9c4,stroke:#f57f17,color:#000
```

---

## 7. Customer & Loyalty Flow

> **Building lasting customer relationships**

```mermaid
flowchart TD
    subgraph LIFECYCLE["Customer Lifecycle"]
        FIRST[First Purchase] --> ENROLL{Enroll in Loyalty?}
        ENROLL --> |Yes| WELCOME["🎉 Welcome!<br/>+50 Bonus Points<br/>Start at Bronze Tier"]
        ENROLL --> |No| REGULAR[Regular Customer]
    end

    subgraph EARNING["Earning Points"]
        WELCOME --> SHOP[Customer Makes Purchase]
        SHOP --> CALC["Calculate Points<br/>₹1 Spent = 1 Base Point<br/>× Tier Multiplier"]
        CALC --> POINTS["Points Added to Balance<br/>Expire After 1 Year"]
        POINTS --> SHOP
    end

    subgraph TIERS["🏆 Tier Progression"]
        POINTS --> TIER_CHECK{Cumulative Points}
        TIER_CHECK --> |"0 - 999"| BRONZE["🥉 Bronze<br/>1.0× Multiplier<br/>0% Discount"]
        TIER_CHECK --> |"1,000 - 4,999"| SILVER["🥈 Silver<br/>1.25× Multiplier<br/>5% Discount"]
        TIER_CHECK --> |"5,000 - 14,999"| GOLD["🥇 Gold<br/>1.5× Multiplier<br/>10% Discount"]
        TIER_CHECK --> |"15,000+"| PLATINUM["💎 Platinum<br/>2.0× Multiplier<br/>15% Discount"]
    end

    subgraph REWARDS["🎁 Redeeming Rewards"]
        POINTS --> CATALOG["Reward Catalog"]
        CATALOG --> R1["Discount Coupon<br/>e.g., 200 pts = ₹50 off"]
        CATALOG --> R2["Free Product<br/>e.g., 500 pts = Free Oil"]
        CATALOG --> R3["Cashback<br/>e.g., 300 pts = ₹30 back"]
    end

    subgraph BIRTHDAY["🎂 Birthday Bonus"]
        WELCOME --> BDAY{Customer's Birthday?}
        BDAY --> |Bronze| B1["+100 Points"]
        BDAY --> |Silver| B2["+200 Points"]
        BDAY --> |Gold| B3["+300 Points"]
        BDAY --> |Platinum| B4["+500 Points"]
    end

    style LIFECYCLE fill:#e8f5e9,stroke:#2e7d32,color:#000
    style EARNING fill:#e3f2fd,stroke:#1565c0,color:#000
    style TIERS fill:#fff3e0,stroke:#e65100,color:#000
    style REWARDS fill:#fce4ec,stroke:#c62828,color:#000
    style BIRTHDAY fill:#f3e5f5,stroke:#6a1b9a,color:#000
```

---

## 8. Supplier & Payment Flow

> **Managing supplier relationships and payments**

```mermaid
flowchart TD
    subgraph SUPPLIER["Supplier Management"]
        REG[Register Supplier] --> DETAILS["Name, Contact<br/>Tax Number, Address<br/>Auto: SUP-20260419-0001"]
    end

    subgraph PURCHASE_CYCLE["Purchase Cycle"]
        DETAILS --> PO[Create Purchase Order]
        PO --> ITEMS["Add Products & Quantities<br/>Set Cost Prices"]
        ITEMS --> SUBMIT[Submit Order]
        SUBMIT --> RECEIVE[Receive Goods]
        RECEIVE --> VERIFY{Quality Check}
        VERIFY --> |OK| ACCEPT[Accept All]
        VERIFY --> |Issues| RETURN["Create Purchase Return<br/>Log Reason, Qty"]
    end

    subgraph LEDGER["📒 Supplier Ledger"]
        ACCEPT --> DEBIT["Debit: Amount Owed<br/>₹50,000 Purchase"]
        RETURN --> CREDIT1["Credit: Return Amount<br/>-₹5,000 Returns"]
        DEBIT & CREDIT1 --> BALANCE["Running Balance<br/>₹45,000 Outstanding"]
    end

    subgraph PAYMENTS["💰 Payments"]
        BALANCE --> PAY{Make Payment}
        PAY --> CASH["Cash Payment"]
        PAY --> BANK["Bank Transfer"]
        PAY --> CHECK["Cheque"]
        CASH & BANK & CHECK --> CREDIT2["Credit: Payment<br/>-₹20,000"]
        CREDIT2 --> NEW_BAL["Updated Balance<br/>₹25,000 Remaining"]
    end

    subgraph STATEMENT["📊 Statements"]
        NEW_BAL --> STMT["Supplier Statement<br/>All Debits & Credits<br/>Date Range Filter<br/>Excel Export"]
    end

    style SUPPLIER fill:#e8f5e9,stroke:#2e7d32,color:#000
    style PURCHASE_CYCLE fill:#e3f2fd,stroke:#1565c0,color:#000
    style LEDGER fill:#fff3e0,stroke:#e65100,color:#000
    style PAYMENTS fill:#fce4ec,stroke:#c62828,color:#000
    style STATEMENT fill:#f3e5f5,stroke:#6a1b9a,color:#000
```

---

## 9. Reporting & Analytics Flow

> **Turning data into business decisions**

```mermaid
flowchart TD
    subgraph DATA_SOURCES["📊 Data Sources"]
        SALES[Every Sale]
        INVENTORY[Stock Levels]
        CUSTOMERS[Customer Activity]
        PURCHASES[Purchase History]
        CASHIERS[Cashier Activity]
    end

    subgraph REPORTS["📈 Available Reports"]
        SALES --> SR["Sales Report<br/>Daily/Weekly/Monthly<br/>By Store, Payment Method<br/>Top Products"]
        SALES & PURCHASES --> PR["Profit Report<br/>Revenue vs Cost<br/>Margin by Category<br/>Top/Bottom Products"]
        INVENTORY --> IR["Inventory Report<br/>ABC Analysis<br/>Turnover Rate<br/>Dead Stock Detection"]
        INVENTORY --> SVR["Stock Valuation<br/>Total Inventory Worth<br/>By Category/Supplier"]
        CUSTOMERS --> CR["Customer Analytics<br/>Lifetime Value<br/>RFM Segmentation<br/>9 Customer Segments"]
        CASHIERS --> CPR["Cashier Performance<br/>Efficiency Score<br/>Revenue per Hour<br/>Variance Analysis"]
        PURCHASES --> SLR["Supplier Ledger<br/>Outstanding Amounts<br/>Payment History"]
        CUSTOMERS --> CLR["Customer Ledger<br/>Receivables<br/>Payment Tracking"]
    end

    subgraph DELIVERY["📬 Report Delivery"]
        SR & PR & IR & SVR & CR & CPR & SLR & CLR --> VIEW["View on Screen"]
        SR & PR & IR & SVR & CR & CPR & SLR & CLR --> EXPORT["Export to Excel"]
        SR & PR & IR & SVR & CR & CPR & SLR & CLR --> SCHEDULE["Schedule Auto-Send<br/>Daily/Weekly/Monthly<br/>Email to Team"]
    end

    subgraph ALERTS["🚨 Smart Alerts"]
        INVENTORY --> A1["Low Stock Alert<br/>Below Reorder Level"]
        SALES --> A2["High-Value Sale Alert<br/>Above Threshold"]
        CASHIERS --> A3["Cashier Variance Alert<br/>Unusual Activity"]
        INVENTORY --> A4["Inventory Discrepancy<br/>Mismatch Detected"]
        SALES --> A5["Sales Target Alert<br/>Goal Reached/Missed"]
    end

    style DATA_SOURCES fill:#e8f5e9,stroke:#2e7d32,color:#000
    style REPORTS fill:#e3f2fd,stroke:#1565c0,color:#000
    style DELIVERY fill:#fff3e0,stroke:#e65100,color:#000
    style ALERTS fill:#fce4ec,stroke:#c62828,color:#000
```

---

## 10. Multi-Store Operations Flow

> **Managing multiple stores from one dashboard**

```mermaid
flowchart TD
    subgraph ORG_LEVEL["🏢 Organization Level"]
        ADMIN[Organization Admin] --> ORG_DASH["Organization Dashboard<br/>All Stores Combined"]
        ORG_DASH --> AGG_SALES["Total Sales Across Stores"]
        ORG_DASH --> AGG_STOCK["Total Inventory Value"]
        ORG_DASH --> AGG_CUST["Total Customers"]
    end

    subgraph STORES["🏪 Individual Stores"]
        ADMIN --> SWITCH["Store Switcher<br/>Switch Context Instantly"]
        SWITCH --> S1["Store: Kathmandu<br/>Own POS, Stock, Team"]
        SWITCH --> S2["Store: Delhi<br/>Own POS, Stock, Team"]
        SWITCH --> S3["Store: Mumbai<br/>Own POS, Stock, Team"]
    end

    subgraph SHARED["Shared Across Stores"]
        S1 & S2 & S3 --> CATALOG["Same Product Catalog"]
        S1 & S2 & S3 --> CUST_DB["Shared Customers"]
        S1 & S2 & S3 --> SUP_DB["Same Suppliers"]
    end

    subgraph UNIQUE["Unique Per Store"]
        S1 --> STOCK1["Own Stock Levels"]
        S2 --> STOCK2["Own Stock Levels"]
        S3 --> STOCK3["Own Stock Levels"]
        S1 --> PRICE1["Custom Pricing<br/>(Optional Overrides)"]
        S1 --> TEAM1["Own Cashiers/Staff"]
    end

    subgraph TRANSFER["🔄 Inter-Store Transfer"]
        S1 --> |"Transfer 50 units"| S2
        S2 --> |"Transfer 30 units"| S3
    end

    subgraph ACCESS["🔐 Access Control"]
        ADMIN --> ROLES["Role-Based Access"]
        ROLES --> R1["Admin: All Stores"]
        ROLES --> R2["Manager: Assigned Stores"]
        ROLES --> R3["Cashier: Own Store Only"]
    end

    style ORG_LEVEL fill:#e3f2fd,stroke:#1565c0,color:#000
    style STORES fill:#e8f5e9,stroke:#2e7d32,color:#000
    style SHARED fill:#fff3e0,stroke:#e65100,color:#000
    style UNIQUE fill:#fce4ec,stroke:#c62828,color:#000
    style TRANSFER fill:#f3e5f5,stroke:#6a1b9a,color:#000
    style ACCESS fill:#fff9c4,stroke:#f57f17,color:#000
```

---

## 11. B2B / Wholesale Flow

> **Serving retail partners and bulk orders**

```mermaid
flowchart TD
    subgraph RETAIL_PARTNER["🏪 Retail Partner Management"]
        REG[Register Retail Store] --> INFO["Store Name, Contact<br/>Address, Google Maps<br/>Photos"]
    end

    subgraph FIELD_VISITS["🚶 Field Visit Tracking"]
        INFO --> VISIT[Log Store Visit]
        VISIT --> ASSESS["Assessment<br/>Stock Available?<br/>Good Display?<br/>Staff Trained?<br/>Competition Present?"]
        ASSESS --> RATING["Rate Store<br/>Footfall / Cooperation<br/>Overall Rating"]
        RATING --> NEXT["Schedule Next Visit"]
    end

    subgraph BULK_ORDERS["📦 Bulk Orders"]
        INFO --> INQUIRY[Bulk Order Inquiry]
        INQUIRY --> PRODUCTS["Select Products<br/>Min: 10 Units Each"]
        PRODUCTS --> QUOTE[Generate Quotation]
        QUOTE --> APPROVE{Approved?}
        APPROVE --> |Yes| INVOICE["Create Invoice<br/>Proforma / Tax Invoice"]
        APPROVE --> |No| REVISE["Revise Quote"]
        REVISE --> QUOTE
    end

    subgraph INVOICING["📄 Invoice Types"]
        INVOICE --> T1["Proforma Invoice<br/>Before Payment"]
        INVOICE --> T2["Tax Invoice<br/>GST/VAT Compliant"]
        INVOICE --> T3["Credit Note<br/>Returns/Adjustments"]
    end

    style RETAIL_PARTNER fill:#e8f5e9,stroke:#2e7d32,color:#000
    style FIELD_VISITS fill:#e3f2fd,stroke:#1565c0,color:#000
    style BULK_ORDERS fill:#fff3e0,stroke:#e65100,color:#000
    style INVOICING fill:#fce4ec,stroke:#c62828,color:#000
```

---

## 12. Complete Business Lifecycle

> **The big picture: how everything connects**

```mermaid
flowchart LR
    subgraph PHASE1["1️⃣ SETUP"]
        direction TB
        A1[Organization] --> A2[Stores]
        A2 --> A3[Team & Roles]
        A3 --> A4[Categories]
        A4 --> A5[Products & Variants]
        A5 --> A6[Suppliers]
    end

    subgraph PHASE2["2️⃣ STOCK"]
        direction TB
        B1[Purchase Order] --> B2[Receive Stock]
        B2 --> B3[Batch Created]
        B3 --> B4[Stock Updated]
        B4 --> B5[Pay Supplier]
    end

    subgraph PHASE3["3️⃣ SELL"]
        direction TB
        C1[Customer Enters] --> C2[Build Cart]
        C2 --> C3[Apply Loyalty]
        C3 --> C4[Accept Payment]
        C4 --> C5[Print Receipt]
    end

    subgraph PHASE4["4️⃣ GROW"]
        direction TB
        D1[Analyze Reports] --> D2[Customer Insights]
        D2 --> D3[Restock Smart]
        D3 --> D4[Loyalty Rewards]
        D4 --> D5[Scale to New Stores]
    end

    PHASE1 ==> PHASE2 ==> PHASE3 ==> PHASE4
    PHASE4 -.-> |"Repeat & Expand"| PHASE2

    style PHASE1 fill:#e8f5e9,stroke:#2e7d32,color:#000
    style PHASE2 fill:#e3f2fd,stroke:#1565c0,color:#000
    style PHASE3 fill:#fff3e0,stroke:#e65100,color:#000
    style PHASE4 fill:#fce4ec,stroke:#c62828,color:#000
```

---

## Pricing Flow (India vs Nepal)

```mermaid
flowchart TD
    subgraph PRICING["Dynamic Pricing Engine"]
        VARIANT[Product Variant] --> COUNTRY{Organization Country}
        COUNTRY --> |India| INDIA["Use MRP India<br/>Currency: ₹ INR<br/>Tax: GST (12%)"]
        COUNTRY --> |Nepal| NEPAL["Use Selling Price Nepal<br/>Currency: रू NPR<br/>Tax: VAT (13%)"]

        INDIA --> STORE_CHECK{Store Override?}
        NEPAL --> STORE_CHECK
        STORE_CHECK --> |Yes| CUSTOM["Use Custom Store Price"]
        STORE_CHECK --> |No| DEFAULT["Use Default Price"]

        CUSTOM & DEFAULT --> TAX["Calculate Tax<br/>India: 5% / 12% / 18%<br/>Nepal: 13%"]
        TAX --> FINAL["Final Price to Customer"]
    end

    style PRICING fill:#e3f2fd,stroke:#1565c0,color:#000
```

---

*These flowcharts can be rendered in any Mermaid-compatible viewer including GitHub, VS Code, and presentation tools.*
