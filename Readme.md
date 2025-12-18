
# NewoAI Integration Module for OpenEMR

This module provides integration between **OpenEMR** and the NewoAI platform.  
Its primary purpose is to enable scheduling functionality by exposing a new REST API endpoint that retrieves **available time slots** for a specific **provider** and **facility** within a given date range.

---

## 🚀 Features
1.Adds a new REST API endpoint:  
  **`GET /apis/default/api/available_slots`**
- Allows clients to query available slots for:
    - **Provider** (`aid`)
    - **Facility** (`fid`)
    - **Date range** (`date_from`, `date_to`)
- Implements strict validation and returns structured JSON responses.
- Requires a new OAuth scope:  
  **`user/available_slots.read`**

2.Adds a new REST API endpoint:
  **`GET /apis/default/api/patient_by_phone`**
- Allows search patients by phone :
    - **Phone** (`aid`)
    - **Facility** (`fid`)
    - **Date range** (`date_from`, `date_to`)
- Implements strict validation and returns structured JSON responses.
- Requires a new OAuth scope:  
  **`user/patient_by_phone.read`**
---

## 🔐 Authorization
To access the endpoint, the authenticated user must have the scope:
**user/available_slots.read**

## 📡 API Endpoint Details
### **Endpoint** GET /apis/default/api/available_slots
#### **Query Parameters**
| Parameter    | Type     | Required | Description                                  |
|-------------|----------|----------|----------------------------------------------|
| `aid`       | string   | Yes      | Provider ID                                 |
| `fid`       | string   | Yes      | Facility ID                                 |
| `date_from` | string   | Yes      | Start date in format `YYYY-MM-DD`          |
| `date_to`   | string   | Yes      | End date in format `YYYY-MM-DD`            |

---

#### ✅ Example Request (cURL)

```bash
curl -X GET \
  "https://your-openemr-instance/apis/default/api/available_slots?aid=123&fid=456&date_from=2025-12-08&date_to=2025-12-09" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

#### ✅ Example Response
```json

[
  {
    "date": "2025-12-08",
    "slots": [
      { "start_time": "09:00", "end_time": "09:15" },
      { "start_time": "09:15", "end_time": "09:30" }
    ]
  }
]

```
## 🛠 Installation

**Prerequisites**:</br>
OpenEMR ≥ 7.0.3.4, PHP ≥ 8.2, Composer


1. Clone the repository into your OpenEMR modules directory: </br>
    ```bash
    git clone https://github.com/twinmind-pro/oe-module-newo-ai.git NewoAI
    composer install --no-dev --classmap-authoritative --optimize-autoloader
    composer archive --format=zip --dir=build
    ```
2. Unzip **build/twinmindpro-oe-newo-ai-<VERSION_NUMBER>.zip** to folder **oe-module-newo-ai** and copy to OpenEMR custom modules directory </br>
   <openemr_installation_directory>//interface/modules/custom_modules/.
3. Log in OpenEMR as Administrator 
4. In top menu chose **Modules** then **Manage Modules**
5. Find under the **Custom Module Listings** module **Newo AI Integration Module v<VERSION_NUMBER>**
6. Click **Install** then **Enable**
7. Create new API client with scope **user/available_slots.read**