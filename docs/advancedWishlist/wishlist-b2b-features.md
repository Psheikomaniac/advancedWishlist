# B2B Features - Advanced Wishlist System

## Overview

The B2B features of the Advanced Wishlist System provide specialized tools for business customers with more complex purchasing processes. These features support team collaboration, approval workflows, and budget management.

## Core Features

### Team Wishlists

```
As a B2B customer, I want to...
- Create wishlists for my team
- Invite and manage team members
- Assign different permissions
- View activity history
```

**Technical Implementation:**

```php
// Entity: TeamWishlist extends Wishlist
class TeamWishlistEntity extends WishlistEntity
{
    protected Collection $members;
    protected ?string $departmentId;
    protected ?float $budgetLimit;
    protected ?\DateTimeInterface $validUntil;

    // Getters & Setters
}

// Entity: TeamMember
class TeamMemberEntity extends Entity
{
    protected string $wishlistId;
    protected string $userId;
    protected string $role; // owner, editor, viewer
    protected ?array $permissions;

    // Getters & Setters
}
```

### Permission System

**Roles:**

1. **Owner**
   - Can manage everything (CRUD)
   - Can add/remove members
   - Can change permissions

2. **Editor**
   - Can add/remove products
   - Can edit notes
   - Cannot manage members

3. **Viewer**
   - Can only view
   - Can comment if allowed

**Permission Table:**

| Action                  | Owner | Editor | Viewer |
|-------------------------|-------|--------|--------|
| Add product             | ✓     | ✓      | -      |
| Remove product          | ✓     | ✓      | -      |
| Manage members          | ✓     | -      | -      |
| Trigger order           | ✓     | ✓*     | -      |
| Add comments            | ✓     | ✓      | ✓      |
| Export wishlists        | ✓     | ✓      | ✓      |

*With appropriate permission

### Approval Workflows

```
As a B2B purchasing manager, I want to...
- Define approval processes for orders
- Set budget limits per wishlist
- Receive notifications about approval requests
- View order history and approval logs
```

**Workflow Model:**

1. **Creation**: Team member creates/fills wishlist
2. **Submission**: Request for approval is submitted
3. **Review**: Manager reviews and approves/rejects
4. **Ordering**: Automatic transfer to cart

**Technical Implementation:**

```php
class ApprovalWorkflowService
{
    public function createApprovalRequest(string $wishlistId, Context $context): ApprovalRequestEntity
    {
        // Check permissions
        // Create approval request
        // Notify approvers
    }

    public function approveRequest(string $requestId, Context $context): void
    {
        // Approval process
        // Optional: Automatic ordering
    }

    public function rejectRequest(string $requestId, string $reason, Context $context): void
    {
        // Rejection process with reason
        // Notify requester
    }
}
```

### Budget Management

```
As a B2B finance manager, I want to...
- Set budget limits for departments
- Track spending per wishlist/department
- Receive warnings on budget overruns
- Export budget reports
```

**Budget Control:**

```php
class BudgetService
{
    public function checkBudget(string $wishlistId, ?float $amount = null): BudgetCheckResult
    {
        // Check if budget is sufficient
        // Give warning at 80% utilization
        // Deny on overrun
    }

    public function setBudget(string $wishlistId, float $amount, Context $context): void
    {
        // Set budget for a wishlist
    }

    public function generateReport(string $departmentId, \DateTimeInterface $startDate, \DateTimeInterface $endDate): BudgetReport
    {
        // Create report on budget usage
    }
}
```

## Import/Export Functions

### CSV Export/Import

```
As a B2B purchaser, I want to...
- Export wishlists as CSV
- Import products from CSV
- Export order history
- Sync data with ERP system
```

**Export Format:**

```csv
ProductNumber;Name;Quantity;UnitPrice;TotalPrice;Note;Priority
SW10001;Product A;5;19.99;99.95;Urgently needed;1
SW10002;Product B;10;9.99;99.90;Standard reorder;3
```

**Technical Implementation:**

```php
class ImportExportService
{
    public function exportWishlist(string $wishlistId, string $format = 'csv'): DownloadableFile
    {
        // Format can be 'csv' or 'excel'
        // Create downloadable file
    }

    public function importProducts(string $wishlistId, UploadedFile $file, ImportOptions $options): ImportResult
    {
        // Process uploaded file
        // Validate products
        // Add them to wishlist
    }
}
```

## Integration with ERP Systems

```
As a B2B administrator, I want to...
- Connect wishlists with our ERP system
- Automate ordering processes
- Sync product data
- Track order status in real-time
```

**Supported ERP Systems:**

- SAP Business One
- Microsoft Dynamics
- Sage
- Odoo
- Custom API Integration

**API Endpoints for ERP Integration:**

```yaml
# ERP API Endpoints
POST   /api/erp/sync-products
POST   /api/erp/create-order
GET    /api/erp/order-status/{orderId}
PUT    /api/erp/update-inventory
```

## B2B-specific DTOs

### TeamWishlistDTO

```php
class TeamWishlistDTO extends WishlistDTO
{
    #[Assert\NotBlank]
    private string $departmentId;

    #[Assert\Type('array')]
    private array $members = [];

    #[Assert\Type('float')]
    #[Assert\PositiveOrZero]
    private ?float $budgetLimit = null;

    #[Assert\Type('\DateTimeInterface')]
    private ?\DateTimeInterface $validUntil = null;

    // Getters & Setters
}
```

### ApprovalRequestDTO

```php
class ApprovalRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $wishlistId;

    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $requesterId;

    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $approverId;

    #[Assert\Type('string')]
    #[Assert\Length(max: 1000)]
    private ?string $comment = null;

    #[Assert\Type('float')]
    #[Assert\Positive]
    private float $totalAmount;

    #[Assert\Type('bool')]
    private bool $convertToOrder = false;

    // Getters & Setters
}
```

## Frontend Components

### Team Management Interface

```vue
<template>
   <div class="team-wishlist-manager">
      <h2>{{ wishlist.name }} - Team Management</h2>

      <div class="team-members">
         <table class="members-table">
            <thead>
            <tr>
               <th>User</th>
               <th>Role</th>
               <th>Added on</th>
               <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="member in members" :key="member.id">
               <td>{{ member.name }}</td>
               <td>
                  <select v-model="member.role" @change="updateMemberRole(member)">
                     <option value="owner">Owner</option>
                     <option value="editor">Editor</option>
                     <option value="viewer">Viewer</option>
                  </select>
               </td>
               <td>{{ formatDate(member.addedAt) }}</td>
               <td>
                  <button @click="removeMember(member)" class="btn-remove">
                     Remove
                  </button>
               </td>
            </tr>
            </tbody>
         </table>

         <div class="add-member">
            <input v-model="newMemberEmail" placeholder="Email Address" />
            <select v-model="newMemberRole">
               <option value="editor">Editor</option>
               <option value="viewer">Viewer</option>
            </select>
            <button @click="addMember" class="btn-add">Add</button>
         </div>
      </div>
   </div>
</template>

<script>
   export default {
      // Component logic
   }
</script>
```

### Approval Workflow Interface

```vue
<template>
   <div class="approval-workflow">
      <div class="approval-status">
         <h3>Approval Status</h3>
         <div :class="['status-badge', statusClass]">
            {{ statusText }}
         </div>
      </div>

      <div v-if="canRequest" class="request-approval">
         <button @click="requestApproval" class="btn-primary">
            Request Approval
         </button>
      </div>

      <div v-if="isPending && canApprove" class="approval-actions">
         <button @click="approve" class="btn-success">Approve</button>
         <button @click="reject" class="btn-danger">Reject</button>
         <textarea v-model="rejectReason" placeholder="Reason (if rejecting)"></textarea>
      </div>

      <div class="approval-history">
         <h3>History</h3>
         <ul class="timeline">
            <li v-for="event in history" :key="event.id" :class="event.type">
               <span class="time">{{ formatDate(event.timestamp) }}</span>
               <span class="action">{{ event.description }}</span>
               <span class="user">{{ event.user }}</span>
            </li>
         </ul>
      </div>
   </div>
</template>
```

## REST API Endpoints

### Team Management

```yaml
# Team API
GET    /api/wishlist/{wishlistId}/team
POST   /api/wishlist/{wishlistId}/team/members
PUT    /api/wishlist/{wishlistId}/team/members/{userId}
DELETE /api/wishlist/{wishlistId}/team/members/{userId}
```

### Approval Workflow

```yaml
# Approval API
POST   /api/wishlist/{wishlistId}/approval-request
GET    /api/approval-requests
GET    /api/approval-requests/{requestId}
PUT    /api/approval-requests/{requestId}/approve
PUT    /api/approval-requests/{requestId}/reject
GET    /api/approval-requests/history
```

### Budget Management

```yaml
# Budget API
GET    /api/wishlist/{wishlistId}/budget
PUT    /api/wishlist/{wishlistId}/budget
GET    /api/department/{departmentId}/budget
GET    /api/budget/reports
```

## Paywall Features

B2B features are primarily available in the following tiers:

### 🏢 **BUSINESS (99€/month)**
- Basic team wishlists (max. 5 members)
- Simple approval workflow
- CSV export/import
- Budget tracking

### 🚀 **ENTERPRISE (199€/month)**
- Unlimited team members
- Multi-level approval workflows
- Cross-departmental wishlists
- ERP integration
- Custom permissions
- Detailed budget reports