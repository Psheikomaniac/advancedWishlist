# B2B Features - Advanced Wishlist System

## Überblick

Die B2B-Funktionen des Advanced Wishlist Systems bieten spezielle Tools für Geschäftskunden mit komplexeren Einkaufsprozessen. Diese Features unterstützen Teamkollaboration, Genehmigungsworkflows und Budget-Management.

## Kernfunktionen

### Team-Wishlists

```
Als B2B-Kunde möchte ich...
- Wishlists für mein Team erstellen
- Teammitglieder einladen und verwalten
- Unterschiedliche Berechtigungen zuweisen
- Aktivitätshistorie einsehen
```

**Technische Umsetzung:**

```php
// Entity: TeamWishlist erweitert Wishlist
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

### Berechtigungssystem

**Rollen:**

1. **Owner**
   - Kann alles verwalten (CRUD)
   - Kann Mitglieder hinzufügen/entfernen
   - Kann Berechtigungen ändern

2. **Editor**
   - Kann Produkte hinzufügen/entfernen
   - Kann Notizen bearbeiten
   - Kann keine Mitglieder verwalten

3. **Viewer**
   - Kann nur ansehen
   - Kann kommentieren, wenn erlaubt

**Berechtigungstabelle:**

| Aktion                  | Owner | Editor | Viewer |
|-------------------------|-------|--------|--------|
| Produkt hinzufügen      | ✓     | ✓      | -      |
| Produkt entfernen       | ✓     | ✓      | -      |
| Mitglieder verwalten    | ✓     | -      | -      |
| Bestellung auslösen     | ✓     | ✓*     | -      |
| Kommentare hinzufügen   | ✓     | ✓      | ✓      |
| Wishlists exportieren   | ✓     | ✓      | ✓      |

*Mit entsprechender Berechtigung

### Genehmigungsworkflows

```
Als B2B-Einkaufsleiter möchte ich...
- Genehmigungsprozesse für Bestellungen definieren
- Budgetlimits pro Wishlist festlegen
- Benachrichtigungen über Genehmigungsanfragen erhalten
- Bestellhistorie und Genehmigungsprotokolle einsehen
```

**Workflow-Modell:**

1. **Erstellung**: Team-Mitglied erstellt/befüllt Wishlist
2. **Einreichung**: Anfrage zur Genehmigung wird gestellt
3. **Prüfung**: Manager überprüft und genehmigt/lehnt ab
4. **Bestellung**: Automatische Überführung in Warenkorb

**Technische Umsetzung:**

```php
class ApprovalWorkflowService
{
    public function createApprovalRequest(string $wishlistId, Context $context): ApprovalRequestEntity
    {
        // Prüfung der Berechtigungen
        // Erstellung einer Approval-Anfrage
        // Benachrichtigung der genehmigenden Personen
    }

    public function approveRequest(string $requestId, Context $context): void
    {
        // Genehmigungsprozess
        // Optional: Automatische Bestellung
    }

    public function rejectRequest(string $requestId, string $reason, Context $context): void
    {
        // Ablehnungsprozess mit Begründung
        // Benachrichtigung des Antragstellers
    }
}
```

### Budget-Management

```
Als B2B-Finanzmanager möchte ich...
- Budgetlimits für Abteilungen festlegen
- Ausgabenverfolgung pro Wishlist/Abteilung
- Warnungen bei Budgetüberschreitung
- Budget-Reports exportieren
```

**Budget-Kontrolle:**

```php
class BudgetService
{
    public function checkBudget(string $wishlistId, ?float $amount = null): BudgetCheckResult
    {
        // Prüft, ob das Budget ausreicht
        // Gibt Warnung bei 80% Ausschöpfung
        // Verweigert bei Überschreitung
    }

    public function setBudget(string $wishlistId, float $amount, Context $context): void
    {
        // Setzt das Budget für eine Wishlist
    }

    public function generateReport(string $departmentId, \DateTimeInterface $startDate, \DateTimeInterface $endDate): BudgetReport
    {
        // Erstellt einen Bericht über Budgetnutzung
    }
}
```

## Import/Export-Funktionen

### CSV-Export/Import

```
Als B2B-Einkäufer möchte ich...
- Wishlists als CSV exportieren
- Produkte aus CSV importieren
- Bestellhistorie exportieren
- Daten mit ERP-System synchronisieren
```

**Export-Format:**

```csv
ProduktNummer;Bezeichnung;Menge;Einzelpreis;Gesamtpreis;Notiz;Priorität
SW10001;Produkt A;5;19.99;99.95;Dringend benötigt;1
SW10002;Produkt B;10;9.99;99.90;Standard-Nachbestellung;3
```

**Technische Umsetzung:**

```php
class ImportExportService
{
    public function exportWishlist(string $wishlistId, string $format = 'csv'): DownloadableFile
    {
        // Format kann 'csv' oder 'excel' sein
        // Erstellt eine herunterladbare Datei
    }

    public function importProducts(string $wishlistId, UploadedFile $file, ImportOptions $options): ImportResult
    {
        // Verarbeitet die hochgeladene Datei
        // Validiert Produkte
        // Fügt sie zur Wishlist hinzu
    }
}
```

## Integration mit ERP-Systemen

```
Als B2B-Administrator möchte ich...
- Wishlists mit unserem ERP-System verbinden
- Bestellprozesse automatisieren
- Produktdaten synchronisieren
- Bestellstatus in Echtzeit verfolgen
```

**Unterstützte ERP-Systeme:**

- SAP Business One
- Microsoft Dynamics
- Sage
- Odoo
- Custom API-Integration

**API-Endpunkte für ERP-Integration:**

```yaml
# ERP-API Endpunkte
POST   /api/erp/sync-products
POST   /api/erp/create-order
GET    /api/erp/order-status/{orderId}
PUT    /api/erp/update-inventory
```

## B2B-spezifische DTOs

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

## Frontend-Komponenten

### Team Management Interface

```vue
<template>
  <div class="team-wishlist-manager">
    <h2>{{ wishlist.name }} - Team Management</h2>

    <div class="team-members">
      <table class="members-table">
        <thead>
          <tr>
            <th>Benutzer</th>
            <th>Rolle</th>
            <th>Hinzugefügt am</th>
            <th>Aktionen</th>
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
                Entfernen
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <div class="add-member">
        <input v-model="newMemberEmail" placeholder="E-Mail Adresse" />
        <select v-model="newMemberRole">
          <option value="editor">Editor</option>
          <option value="viewer">Viewer</option>
        </select>
        <button @click="addMember" class="btn-add">Hinzufügen</button>
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
      <h3>Genehmigungsstatus</h3>
      <div :class="['status-badge', statusClass]">
        {{ statusText }}
      </div>
    </div>

    <div v-if="canRequest" class="request-approval">
      <button @click="requestApproval" class="btn-primary">
        Genehmigung anfordern
      </button>
    </div>

    <div v-if="isPending && canApprove" class="approval-actions">
      <button @click="approve" class="btn-success">Genehmigen</button>
      <button @click="reject" class="btn-danger">Ablehnen</button>
      <textarea v-model="rejectReason" placeholder="Begründung (bei Ablehnung)"></textarea>
    </div>

    <div class="approval-history">
      <h3>Verlauf</h3>
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

## REST API Endpunkte

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

## Paywall-Features

B2B-Funktionen sind hauptsächlich in den folgenden Tarifen verfügbar:

### 🏢 **BUSINESS (99€/Monat)**
- Grundlegende Team-Wishlists (max. 5 Mitglieder)
- Einfacher Genehmigungsworkflow
- CSV-Export/Import
- Budget-Tracking

### 🚀 **ENTERPRISE (199€/Monat)**
- Unbegrenzte Team-Mitglieder
- Mehrstufige Genehmigungsworkflows
- Abteilungsübergreifende Wishlists
- ERP-Integration
- Benutzerdefinierte Berechtigungen
- Detaillierte Budget-Reports
