# **Product Requirements Document (PRD)**

**Project Name:** PolitiMetrix Data Pipeline
**Version:** 1.0
**Status:** Draft

## **1. Executive Summary**

The goal is to build a unified data ingestion engine ("The Scraper") that automates the collection of heterogeneous datasets—political finance, election results, public opinion, and economic indicators. The system will normalize these disparate data sources into a single database to enable correlation analysis (e.g., "Does inflation impact incumbent fundraising?").

## **2. Problem Statement**

Currently, political and economic data exist in silos:

* **FEC:** JSON API (Granular financial transactions).
* **FRED:** XML/JSON API (Time-series economic metrics).
* **Polling:** CSV/GitHub (Aggregated sentiment).
* **Election Results:** HTML/PDF/CSV (Static event data).

Manually merging these is error-prone. We need an automated ETL (Extract, Transform, Load) pipeline.

## **3. Data Source Strategy**

The system will ingest data from three primary "Connectors":

| Connector ID | Source Name | Type | Key Data Points | Access Method |
| --- | --- | --- | --- | --- |
| **P-01** | **FEC** | API | Candidate fundraising, Cash-on-hand, PAC spending. | `requests` (API Key) |
| **E-01** | **FRED** | API | CPI (Inflation), Unemployment Rate, GDP, Consumer Confidence. | `pandas_datareader` or `requests` |
| **S-01** | **538 / RCP** | CSV/Web | Candidate polling averages, Presidential approval ratings. | `pandas.read_csv` (GitHub URL) |

## **4. Functional Requirements**

### **4.1 The Controller (Orchestrator)**

* **FR-01:** The system must have a central `main.py` script that triggers all individual scrapers.
* **FR-02:** The system must handle rate limits (sleep timers) automatically to avoid IP bans.
* **FR-03:** The system must allow the user to specify a date range (e.g., `start_date="2020-01-01"`, `end_date="2024-11-05"`).

### **4.2 Data Normalization (The "Join" Logic)**

The biggest challenge is that Economic data is *Monthly/Quarterly* while Election data is *Daily/Event-based*.

* **FR-04:** The system must "resample" or "forward-fill" economic data to match the daily frequency of polling/fundraising data.
* **FR-05:** All currency values must be converted to a standard float format (remove `$`, `,`).

### **4.3 Storage**

* **FR-06:** MVP Output: A single master `.csv` file (`master_dataset.csv`).
* **FR-07:** Scalable Output: A SQLite or PostgreSQL database with tables linked by `date`.

---

## **5. Technical Architecture & Schema**

### **5.1 Unified Schema Design**

We will create a "Master Table" where every row represents a snapshot in time.

| Date (PK) | Candidate | Party | Poll_Avg (%) | Cash_On_Hand ($) | Inflation_Rate (%) | Unemployment (%) |
| --- | --- | --- | --- | --- | --- | --- |
| 2024-01-01 | Smith | R | 45.2 | 1,200,000 | 3.4 | 3.7 |
| 2024-01-01 | Jones | D | 44.8 | 950,000 | 3.4 | 3.7 |

### **5.2 Folder Structure**

```text
/politimetrix_scraper
│
├── /extractors          # The "Spiders"
│   ├── __init__.py
│   ├── fec_client.py    # Handles FEC API logic
│   ├── fred_client.py   # Handles Economic data logic
│   └── polling.py       # Handles 538 GitHub/CSV logic
│
├── /data                # Raw and processed storage
│   ├── raw/
│   └── processed/
│
├── config.py            # API Keys and URLs
├── requirements.txt     # Dependencies
└── main.py              # The "Controller" script

```

---

## **6. Implementation Plan (The Code Skeleton)**

Here is how the **Unified Controller (`main.py`)** should look. It imports logic from the sub-modules defined in the folder structure above.

```python
import pandas as pd
from extractors import fec_client, fred_client, polling

# --- CONFIGURATION ---
START_DATE = '2023-01-01'
END_DATE = '2024-12-31'

def run_pipeline():
    print("🚀 Starting PolitiMetrix Pipeline...")

    # 1. FETCH ECONOMIC DATA (The Baseline)
    # Get Inflation (CPIAUCSL) and Unemployment (UNRATE)
    print("Step 1: Fetching Economic Indicators (FRED)...")
    econ_df = fred_client.get_indicators(['CPIAUCSL', 'UNRATE'], START_DATE, END_DATE)
    # Econ data is usually monthly. Resample to daily to match polling.
    econ_df = econ_df.resample('D').ffill() 
    
    # 2. FETCH POLITICAL FINANCE (The Candidates)
    print("Step 2: Fetching Candidate Finances (FEC)...")
    finance_df = fec_client.get_presidential_totals(year=2024)
    # Note: FEC data is often quarterly summaries, logic needed to map to timeline
    
    # 3. FETCH POLLING DATA (The Sentiment)
    print("Step 3: Fetching Polling Data (FiveThirtyEight)...")
    polls_df = polling.get_polls(election='presidential', year=2024)

    # 4. DATA HARMONIZATION (The Merge)
    print("Step 4: Merging Datasets...")
    
    # Merge Polls with Econ Data on 'date'
    # Use 'left' merge to keep all polling days, attaching econ stats to those days
    master_df = pd.merge(polls_df, econ_df, left_on='end_date', right_index=True, how='left')

    # 5. EXPORT
    output_filename = 'data/processed/unified_election_econ_data.csv'
    master_df.to_csv(output_filename)
    print(f"✅ Success! Unified dataset saved to {output_filename}")

if __name__ == "__main__":
    run_pipeline()

```

## **7. Next Steps for You**

1. **Environment Setup:** Create the folder structure above.
2. **API Keys:** Register for **FRED** (St. Louis Fed) and **FEC** (re-use the one from the previous step).
3. **Build Phase:**
* Start by writing `fred_client.py` (easiest).
* Then `polling.py` (Pandas read CSV).
* Finally `fec_client.py` (requires pagination logic).


