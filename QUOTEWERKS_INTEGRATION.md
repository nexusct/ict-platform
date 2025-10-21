# QuoteWerks ↔ ICT Platform Integration
## 10 Workflow Improvements + Bidirectional Sync

---

## 🔗 Integration Overview

**QuoteWerks** is a leading sales quoting software that integrates with PSA tools. This document outlines the complete integration between QuoteWerks and ICT Platform.

### Integration Benefits
- **Eliminate double data entry** - Quote → Project automatic creation
- **Real-time sync** - Bidirectional data flow
- **Automated workflows** - From quote acceptance to project completion
- **Financial tracking** - Quote vs actual cost analysis
- **Inventory sync** - Real-time stock levels in quotes

---

## 📋 10 Workflow Improvements

### 1. **Auto-Create Projects from Accepted Quotes**

**Workflow:**
```
QuoteWerks: Quote Accepted
    ↓
ICT Platform: Auto-create Project
    ↓
- Import all line items as tasks
- Set project budget from quote total
- Assign default project manager
- Create initial schedule
- Generate material requirements
```

**Implementation:**
- Webhook from QuoteWerks on quote status change
- Parse quote XML/JSON
- Create project with all metadata
- Link quote ID to project for tracking

**Data Mapping:**
```json
{
  "quotewerks_field": "ict_platform_field",
  "QuoteNumber": "project_number",
  "CustomerName": "client_name",
  "TotalPrice": "budget_amount",
  "DateCreated": "start_date",
  "SalesRep": "project_manager_id",
  "LineItems": "project_tasks[]"
}
```

---

### 2. **Real-Time Inventory Sync for Accurate Quoting**

**Workflow:**
```
ICT Platform: Update inventory quantity
    ↓
QuoteWerks: Real-time stock level update
    ↓
Sales rep sees accurate availability
    ↓
Prevents over-selling
```

**Features:**
- **Live stock levels** in QuoteWerks product search
- **Reservation system** - Hold stock when quote created
- **Auto-release** - Release held stock if quote expires
- **Multi-location** - Show stock by warehouse
- **Lead time display** - Show ETA for out-of-stock items

**API Endpoints:**
```
GET /api/v1/inventory/{sku}/availability
POST /api/v1/inventory/{sku}/reserve
DELETE /api/v1/inventory/{sku}/release
```

---

### 3. **Automated Purchase Orders from Quotes**

**Workflow:**
```
QuoteWerks: Quote includes items not in stock
    ↓
ICT Platform: Flag items needing PO
    ↓
Auto-generate draft PO
    ↓
Manager approves PO
    ↓
Send to supplier
    ↓
Update QuoteWerks with ETA
```

**Features:**
- **Smart vendor selection** - Choose based on price/lead time
- **Bulk PO creation** - Consolidate multiple quotes
- **Approval workflow** - Route to appropriate approver
- **ETA tracking** - Update quote with delivery date
- **Cost tracking** - Compare quoted vs actual costs

---

### 4. **Dynamic Pricing with Cost+ Markup Calculator**

**Workflow:**
```
QuoteWerks: Building quote
    ↓
ICT Platform: Provide real-time cost data
    ↓
- Current inventory costs
- Historical project costs
- Labor rates by skill level
- Overhead allocation
    ↓
QuoteWerks: Apply markup rules
    ↓
Generate accurate quote
```

**Pricing Intelligence:**
- **Cost history** - Average costs over time
- **Market rates** - Compare to industry standards
- **Margin analysis** - Show profit margins
- **Competitor pricing** - If available
- **Volume discounts** - Auto-apply tiers

**API Response:**
```json
{
  "sku": "CAT6-1000FT",
  "current_cost": 89.99,
  "average_cost_3mo": 92.50,
  "market_rate": 95.00,
  "in_stock": 45,
  "preferred_vendor": "Acme Electrical",
  "lead_time_days": 2,
  "suggested_price": 125.00,
  "min_price": 108.00,
  "target_margin": 28
}
```

---

### 5. **Labor Estimation from Historical Data**

**Workflow:**
```
QuoteWerks: Add service line item
    ↓
ICT Platform: Analyze similar past projects
    ↓
- Find projects with matching criteria
- Calculate average hours
- Adjust for complexity factors
- Consider team efficiency
    ↓
QuoteWerks: Suggest labor hours
    ↓
Sales rep can accept or adjust
```

**Machine Learning Factors:**
- Project type and size
- Location/site conditions
- Team skill levels
- Historical accuracy
- Seasonal variations
- Client-specific patterns

**Smart Suggestions:**
```json
{
  "service": "Panel Upgrade 200A",
  "estimated_hours": 8.5,
  "confidence": 0.85,
  "based_on": "12 similar projects",
  "range": {
    "min": 7.0,
    "max": 11.0,
    "median": 8.5
  },
  "factors": {
    "site_complexity": 1.2,
    "team_experience": 0.9,
    "travel_time": 1.5
  }
}
```

---

### 6. **Automated Follow-Up on Pending Quotes**

**Workflow:**
```
QuoteWerks: Quote sent to customer
    ↓
ICT Platform: Schedule follow-ups
    ↓
Day 3: Email reminder
Day 7: Phone call task
Day 14: Last chance email
Day 21: Archive quote
    ↓
Track conversion rates
Identify bottlenecks
```

**Features:**
- **Smart scheduling** - Based on quote value/priority
- **Multi-channel** - Email, SMS, phone task
- **Templates** - Customizable messages
- **Tracking** - Open rates, click rates
- **Escalation** - Alert manager if no response
- **Analytics** - Quote-to-project conversion funnel

---

### 7. **Quote Versioning and Change Tracking**

**Workflow:**
```
QuoteWerks: Quote revision created
    ↓
ICT Platform: Track all versions
    ↓
- Log all changes
- Calculate delta costs
- Notify relevant parties
- Compare versions side-by-side
    ↓
Customer accepts Version 3
    ↓
Create project from final version
```

**Change Tracking:**
- **Visual diff** - Show what changed
- **Cost impact** - How changes affect price
- **Timeline** - When each version created
- **Audit trail** - Who made changes
- **Approval history** - Who approved what

**Version Comparison:**
```
Quote #1234 - Revision History

Version 1 → Version 2:
  + Added: 500ft CAT6 cable ($450)
  - Removed: WiFi AP upgrade ($380)
  ~ Modified: Labor hours 16→20 (+$400)
  Net Change: +$470 (+8.5%)

Version 2 → Version 3:
  ~ Modified: Discount 10%→15% (-$325)
  Net Change: -$325 (-5.2%)

Final Accepted: Version 3
  Total: $6,145.00
  Margin: 32%
```

---

### 8. **Client Self-Service Quote Approval Portal**

**Workflow:**
```
QuoteWerks: Generate quote
    ↓
ICT Platform: Create approval link
    ↓
Send branded email to client
    ↓
Client Portal:
- View quote details
- Download PDF
- Ask questions (comments)
- Select options (if applicable)
- E-sign acceptance
- Make deposit payment
    ↓
Auto-create project on acceptance
```

**Portal Features:**
- **Responsive design** - Mobile friendly
- **Interactive** - Choose options, upgrades
- **Payment gateway** - Accept deposits
- **Document upload** - Client can attach files
- **Live chat** - Connect with sales rep
- **Status tracking** - Check approval progress

**Client View:**
```
┌─────────────────────────────────┐
│ Quote #1234 - Status: Pending   │
│                                  │
│ Office Electrical Upgrade        │
│ Prepared by: John Smith          │
│ Valid until: Dec 31, 2025        │
│                                  │
│ Subtotal:      $5,500.00         │
│ Tax (8%):        $440.00         │
│ Total:         $5,940.00         │
│                                  │
│ [View Details] [Download PDF]    │
│ [Accept Quote] [Request Changes] │
└─────────────────────────────────┘
```

---

### 9. **Multi-Phase Project Quotes with Milestones**

**Workflow:**
```
QuoteWerks: Create phased quote
    ↓
Phase 1: Design & Planning
Phase 2: Rough-in Installation
Phase 3: Trim & Finish
Phase 4: Testing & Commissioning
    ↓
ICT Platform: Create project with milestones
    ↓
- Separate budget per phase
- Resource allocation by phase
- Payment schedule tied to milestones
- Phase completion triggers billing
```

**Milestone Management:**
- **Dependencies** - Phase 2 can't start until Phase 1 complete
- **Partial billing** - Invoice upon phase completion
- **Budget tracking** - Track spending per phase
- **Schedule** - Gantt chart with phases
- **Approvals** - Client approval required per phase

**Phase Structure:**
```json
{
  "quote_id": "Q-1234",
  "phases": [
    {
      "phase": 1,
      "name": "Design & Planning",
      "budget": 5000,
      "duration_days": 14,
      "payment_trigger": "phase_start",
      "payment_percentage": 25,
      "tasks": [
        "Site survey",
        "Create drawings",
        "Obtain permits"
      ]
    },
    {
      "phase": 2,
      "name": "Rough-in Installation",
      "budget": 15000,
      "duration_days": 21,
      "payment_trigger": "phase_complete",
      "payment_percentage": 40,
      "dependencies": [1]
    }
  ]
}
```

---

### 10. **Quote Performance Analytics Dashboard**

**Workflow:**
```
QuoteWerks: All quote activity
    ↓
ICT Platform: Aggregate and analyze
    ↓
Dashboard shows:
- Quote win/loss ratios
- Average quote value
- Quote-to-project conversion time
- Most quoted products
- Margin analysis by project type
- Sales rep performance
- Quote accuracy (quoted vs actual)
    ↓
Insights for business decisions
```

**Key Metrics:**
- **Conversion Rate** - % of quotes that become projects
- **Quote Velocity** - Time from quote to acceptance
- **Accuracy Score** - How close quotes are to actual costs
- **Pipeline Value** - Total value of pending quotes
- **Lost Reasons** - Why quotes were rejected
- **Profitability** - Margin by project type, client, rep

**Dashboard Visualization:**
```
┌───────────────────────────────────────┐
│ Quote Performance - Last 30 Days      │
├───────────────────────────────────────┤
│ 📊 Win Rate: 42% (32 of 76 quotes)   │
│ 💰 Avg Quote Value: $8,450            │
│ ⏱️  Avg Close Time: 12 days            │
│ 📈 Pipeline Value: $245,000           │
├───────────────────────────────────────┤
│ Top Performers:                       │
│ 1. John Smith - 12 wins - $98K       │
│ 2. Jane Doe - 10 wins - $85K         │
├───────────────────────────────────────┤
│ Lost Reasons:                         │
│ • Price too high - 35%                │
│ • Timing - 28%                        │
│ • Went with competitor - 22%          │
│ • Budget constraints - 15%            │
└───────────────────────────────────────┘
```

---

## 🔄 Bidirectional Sync Architecture

### Data Flow Diagram

```
┌──────────────┐         ┌──────────────┐
│  QuoteWerks  │◄───────►│ ICT Platform │
└──────────────┘         └──────────────┘
       │                        │
       ├─ Quotes                ├─ Projects
       ├─ Customers             ├─ Clients
       ├─ Products              ├─ Inventory
       ├─ Pricing               ├─ Costs
       └─ Sales Reps            └─ Users

Sync Methods:
1. REST API (primary)
2. Webhooks (real-time)
3. Database connector (optional)
4. CSV import/export (fallback)
```

### Sync Frequency

| Data Type | Direction | Method | Frequency |
|-----------|-----------|--------|-----------|
| **Inventory Levels** | ICT → QW | Real-time API | Instant |
| **Product Costs** | ICT → QW | Scheduled | Every 15 min |
| **Quote Acceptance** | QW → ICT | Webhook | Instant |
| **Customer Data** | Both | Scheduled | Every 1 hour |
| **Project Updates** | ICT → QW | Real-time API | Instant |

### Conflict Resolution

**Rule-Based Priority:**
1. **Customer data** - QuoteWerks is master
2. **Inventory** - ICT Platform is master
3. **Pricing** - ICT Platform is master
4. **Projects** - ICT Platform is master

**Manual Review Queue:**
- Conflicts flagged for review
- Side-by-side comparison
- Choose winner or merge
- Audit log maintained

---

## 🛠️ Technical Implementation

### QuoteWerks API Configuration

**Authentication:**
```xml
<QuoteWerksAPI>
  <Authentication>
    <Username>ict_platform_api</Username>
    <Password>secure_password</Password>
    <Token>api_token_here</Token>
  </Authentication>
  <Endpoints>
    <Base>https://your-quotewerks-server.com/api/</Base>
  </Endpoints>
</QuoteWerksAPI>
```

### ICT Platform → QuoteWerks Connector

**File:** `/includes/integrations/quotewerks/class-ict-quotewerks-adapter.php`

```php
<?php
class ICT_QuoteWerks_Adapter {

    private $api_url;
    private $api_key;
    private $api_secret;

    /**
     * Sync inventory to QuoteWerks
     */
    public function sync_inventory_to_quotewerks( $items ) {
        foreach ( $items as $item ) {
            $data = array(
                'ProductCode' => $item['sku'],
                'Description' => $item['item_name'],
                'Cost' => $item['unit_cost'],
                'Price' => $item['unit_price'],
                'QuantityOnHand' => $item['quantity_available'],
                'Vendor' => $this->get_vendor_name( $item['supplier_id'] ),
            );

            $this->api_call( 'POST', '/products/update', $data );
        }
    }

    /**
     * Import quote from QuoteWerks
     */
    public function import_quote( $quote_id ) {
        $quote = $this->api_call( 'GET', "/quotes/{$quote_id}" );

        // Create project
        $project_data = array(
            'project_name' => $quote['QuoteName'],
            'client_id' => $this->get_or_create_client( $quote['Customer'] ),
            'project_number' => $quote['QuoteNumber'],
            'budget_amount' => $quote['Total'],
            'status' => 'pending',
            'notes' => "Created from QuoteWerks Quote #{$quote['QuoteNumber']}",
        );

        $project_id = $this->create_project( $project_data );

        // Import line items as tasks
        foreach ( $quote['LineItems'] as $item ) {
            $this->create_project_task( $project_id, $item );
        }

        // Create inventory reservations
        $this->reserve_inventory_for_project( $project_id, $quote['LineItems'] );

        return $project_id;
    }

    /**
     * Update quote status in QuoteWerks
     */
    public function update_quote_status( $quote_id, $status, $notes = '' ) {
        $data = array(
            'QuoteID' => $quote_id,
            'Status' => $status,
            'StatusNotes' => $notes,
            'LastUpdated' => current_time( 'mysql' ),
        );

        return $this->api_call( 'POST', '/quotes/updatestatus', $data );
    }

    /**
     * Send project completion data back to QuoteWerks
     */
    public function sync_project_completion( $project_id ) {
        $project = $this->get_project( $project_id );
        $quote_id = get_post_meta( $project_id, 'quotewerks_quote_id', true );

        if ( ! $quote_id ) {
            return false;
        }

        $actual_costs = $this->calculate_actual_costs( $project_id );

        $data = array(
            'QuoteID' => $quote_id,
            'ProjectStatus' => 'Completed',
            'ActualCost' => $actual_costs['total_cost'],
            'ActualHours' => $actual_costs['total_hours'],
            'CompletionDate' => $project['completed_date'],
            'Variance' => $project['budget_amount'] - $actual_costs['total_cost'],
            'VariancePercent' => (($actual_costs['total_cost'] - $project['budget_amount']) / $project['budget_amount']) * 100,
        );

        return $this->api_call( 'POST', '/projects/complete', $data );
    }

    private function api_call( $method, $endpoint, $data = array() ) {
        $url = $this->api_url . $endpoint;

        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode( $data ),
            'timeout' => 30,
        );

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            $this->log_error( 'QuoteWerks API Error', $response->get_error_message() );
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        return json_decode( $body, true );
    }
}
```

### Webhook Receiver

**Endpoint:** `/wp-json/ict/v1/webhooks/quotewerks`

```php
<?php
class ICT_QuoteWerks_Webhook_Receiver {

    public function handle_webhook( $request ) {
        $body = $request->get_body();
        $signature = $request->get_header( 'X-QuoteWerks-Signature' );

        // Verify signature
        if ( ! $this->verify_signature( $body, $signature ) ) {
            return new WP_Error( 'invalid_signature', 'Invalid webhook signature', array( 'status' => 401 ) );
        }

        $data = json_decode( $body, true );
        $event_type = $data['EventType'];

        switch ( $event_type ) {
            case 'Quote.Accepted':
                $this->handle_quote_accepted( $data );
                break;

            case 'Quote.Rejected':
                $this->handle_quote_rejected( $data );
                break;

            case 'Quote.Revised':
                $this->handle_quote_revised( $data );
                break;

            case 'Customer.Created':
                $this->handle_customer_created( $data );
                break;

            case 'Customer.Updated':
                $this->handle_customer_updated( $data );
                break;
        }

        return array( 'success' => true, 'message' => 'Webhook processed' );
    }

    private function handle_quote_accepted( $data ) {
        $quote_id = $data['QuoteID'];

        // Import quote as project
        $adapter = new ICT_QuoteWerks_Adapter();
        $project_id = $adapter->import_quote( $quote_id );

        // Send notification
        $this->notify_project_manager( $project_id, 'New project created from accepted quote' );

        // Log activity
        $this->log_activity( 'Quote Accepted', "Quote #{$quote_id} accepted, created project #{$project_id}" );
    }
}
```

---

## 📊 Quote Accuracy Tracking

### Actual vs Quoted Report

**Track variance for continuous improvement:**

```sql
SELECT
    p.project_number,
    p.project_name,
    p.budget_amount as quoted_amount,
    SUM(te.billable_hours * te.hourly_rate) +
        SUM(po.total_amount) as actual_cost,
    (SUM(te.billable_hours * te.hourly_rate) + SUM(po.total_amount) - p.budget_amount) as variance,
    ((SUM(te.billable_hours * te.hourly_rate) + SUM(po.total_amount) - p.budget_amount) / p.budget_amount * 100) as variance_percent
FROM wp_ict_projects p
LEFT JOIN wp_ict_time_entries te ON te.project_id = p.id
LEFT JOIN wp_ict_purchase_orders po ON po.project_id = p.id
WHERE p.status = 'completed'
GROUP BY p.id
ORDER BY variance_percent DESC;
```

**Feed this data back to QuoteWerks to improve future quotes!**

---

## 🎯 Success Metrics

### KPIs to Track

1. **Quote Acceptance Rate** - Target: >40%
2. **Quote-to-Project Time** - Target: <7 days
3. **Quote Accuracy** - Target: ±10% variance
4. **Double Entry Elimination** - Target: 100%
5. **Time Savings** - Target: 5+ hours/week
6. **Revenue from Faster Quoting** - Target: 15% increase
7. **Customer Satisfaction** - Target: >4.5/5 rating
8. **Data Sync Errors** - Target: <1% failure rate

---

## ✅ Implementation Checklist

### Phase 1: Basic Integration (Week 1-2)
- [ ] Configure QuoteWerks API access
- [ ] Set up authentication
- [ ] Test basic connectivity
- [ ] Map data fields
- [ ] Create quote import function
- [ ] Test with sample quotes

### Phase 2: Automation (Week 3-4)
- [ ] Set up webhooks
- [ ] Configure auto-project creation
- [ ] Implement inventory sync
- [ ] Set up pricing sync
- [ ] Configure approval workflows
- [ ] Test end-to-end workflows

### Phase 3: Advanced Features (Week 5-6)
- [ ] Implement labor estimation
- [ ] Set up analytics dashboard
- [ ] Create client portal
- [ ] Configure automated follow-ups
- [ ] Implement multi-phase quotes
- [ ] Set up change tracking

### Phase 4: Optimization (Week 7-8)
- [ ] Performance tuning
- [ ] Error handling improvements
- [ ] User training
- [ ] Documentation
- [ ] Monitor and adjust
- [ ] Gather feedback

---

**Next:** [Enhanced Zoho Sync Improvements](ZOHO_SYNC_ENHANCEMENTS.md)

