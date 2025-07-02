# Product Requirements Document: Advanced Wishlist System für Shopware 6

## 1. Executive Summary

### Produktvision
Das Advanced Wishlist System transformiert Shopware 6 Shops durch ein vollständiges Wunschlisten-Ökosystem, das die fehlende Core-Funktionalität nicht nur nachrüstet, sondern moderne Social Commerce Features integriert und messbare Conversion-Steigerungen ermöglicht.

### Geschäftsziele
- **Marktführerschaft**: Erste vollständige Wishlist-Lösung für Shopware 6
- **Revenue Target**: 500+ zahlende Kunden in 12 Monaten
- **MRR-Ziel**: 50.000€ nach Jahr 1
- **Conversion-Steigerung**: +15-25% für Plugin-Nutzer nachweisbar

### Unique Selling Proposition
"Mehr als eine Wunschliste - Ein Conversion-Booster mit Social Shopping DNA"

## 2. Marktanalyse & Opportunity

### Problem Statement
- **GitHub Issue #253**: Über 200+ Upvotes für Wishlist-Feature
- **Community-Frustration**: "Wie kann ein modernes E-Commerce System keine Wishlist haben?"
- **Conversion-Verlust**: Shops verlieren 8-12% potentielle Käufer ohne Wishlist
- **B2B-Bedarf**: Merkzettel für Großbestellungen fehlt komplett

### Wettbewerbsanalyse
| Konkurrent | Preis | Stärken | Schwächen |
|------------|-------|---------|-----------|
| Keine direkte Konkurrenz | - | - | Markt ist komplett offen |
| Magento Wishlist | Built-in | Kostenlos | Nicht für Shopware |
| WooCommerce Plugins | $49-199 | Viele Features | Andere Plattform |

### Zielgruppen
1. **B2C Fashion & Lifestyle** (40%)
   - Hochzeitsregister, Geburtstagslisten
   - Social Sharing essentiell
   
2. **B2B Großhändler** (35%)
   - Merkzettel für Wiederbestellungen
   - Team-Kollaboration bei Einkauf
   
3. **Specialty Retailers** (25%)
   - Sammler-Communities
   - Limitierte Editionen vormerken

## 3. Feature-Spezifikation

### Core Features (MVP - Version 1.0)

#### 3.1 Wishlist Management
```
Als Kunde möchte ich...
- Produkte mit einem Klick zur Wishlist hinzufügen
- Multiple Wishlists erstellen und benennen
- Produkte zwischen Listen verschieben
- Notizen zu Produkten hinzufügen
- Prioritäten/Ranking festlegen
```

**Technische Umsetzung:**
- Entity: `wishlist`, `wishlist_item`, `wishlist_share`
- API Endpoints: REST & Store-API
- Frontend: Vue.js 3 Components
- Echtzeit-Sync über WebSockets

#### 3.2 Social Sharing
```
Als Kunde möchte ich...
- Wishlists per Link teilen (öffentlich/privat)
- QR-Code für Offline-Sharing generieren
- WhatsApp/Email Integration
- Facebook/Instagram Share-Buttons
- Datenschutz-Einstellungen pro Liste
```

**Share-Optionen:**
- Öffentlicher Link mit Passwort-Option
- Zeitlich begrenzte Links
- Read-only oder Kollaborativ
- Einbettbare Widgets für Blogs

#### 3.3 Guest Wishlist
```
Als Gast möchte ich...
- Wishlist ohne Registrierung nutzen
- Liste nach Registrierung übernehmen
- Cookie-basierte Speicherung (GDPR-konform)
- Email-Erinnerung für Wishlist-Items
```

### Advanced Features (Version 1.1+)

#### 3.4 Price Monitoring
```
Als Kunde möchte ich...
- Preisalarme für Wishlist-Items setzen
- Verfügbarkeits-Benachrichtigungen
- Sale-Alerts für gemerkete Produkte
- Historische Preisentwicklung sehen
```

#### 3.5 Analytics Dashboard
```
Als Shop-Betreiber möchte ich...
- Top Wishlist-Produkte analysieren
- Conversion-Rate von Wishlist zu Kauf
- Sharing-Statistiken einsehen
- Abandoned Wishlist Recovery
```

#### 3.6 B2B Features
```
Als B2B-Kunde möchte ich...
- Team-Wishlists mit Rollen
- Genehmigungsworkflows
- Budget-Limits pro Liste
- CSV-Export/Import
```

## 4. Paywall-Konzept & Monetarisierung

### Tier-Struktur

#### 🆓 **BASIC (Kostenlos)**
**Ziel**: Adoption fördern, Vertrauen aufbauen
- 1 Wishlist pro Kunde
- Max. 50 Produkte
- Basic Sharing (Link only)
- 30 Tage Cookie-Speicherung für Gäste
- Community Support
- "Powered by" Branding

#### 💎 **PROFESSIONAL (49€/Monat)**
**Ziel**: Kleine bis mittlere Shops
- Unlimited Wishlists
- Unlimited Produkte
- Alle Sharing-Optionen
- Price Drop Alerts
- Guest Wishlist (90 Tage)
- Email-Benachrichtigungen
- Basic Analytics
- Priority Email Support
- White-Label Option (+20€)

#### 🏢 **BUSINESS (99€/Monat)**
**Ziel**: Wachsende Shops mit Fokus auf Conversion
- Alles aus Professional
- Advanced Analytics & Reports
- A/B Testing für Wishlist-Buttons
- Abandoned Wishlist Recovery
- API-Zugang
- Multi-Language Support
- Custom Email Templates
- Live Chat Support
- 2 Entwicklerstunden/Monat inklusive

#### 🚀 **ENTERPRISE (199€/Monat + Setup)**
**Ziel**: Große Shops, B2B, Multi-Channel
- Alles aus Business
- B2B Team Features
- Multi-Shop/Mandanten
- Custom Integrations
- SSO/SAML Support
- Dedicated Account Manager
- SLA garantiert
- Custom Development
- On-Premise Option

### Upselling-Strategie

#### Add-Ons (Zusätzlich buchbar)
- **WhatsApp Business Integration**: +29€/Monat
- **Advanced B2B Module**: +49€/Monat
- **KI-Powered Recommendations**: +39€/Monat
- **Extended Analytics**: +19€/Monat
- **Premium Support**: +99€/Monat

#### Usage-Based Pricing
- **API Calls**: 10k inkl., dann 10€ pro 10k
- **Email Notifications**: 1k inkl., dann 5€ pro 1k
- **Storage**: 1GB inkl., dann 5€ pro GB

### Trial & Activation Strategy
1. **30 Tage Full Trial** (alle Business Features)
2. **Automatisches Downgrade** auf gewählten Plan
3. **Activation Incentive**: 20% Rabatt für Jahresvorauszahlung
4. **Referral Program**: 30% Commission für 12 Monate

## 5. Technische Architektur

### Backend Architecture
```
/src
├── Core/
│   ├── Content/
│   │   ├── Wishlist/
│   │   │   ├── WishlistDefinition.php
│   │   │   ├── WishlistEntity.php
│   │   │   └── WishlistCollection.php
│   │   └── WishlistItem/
│   ├── Api/
│   │   ├── WishlistController.php
│   │   └── WishlistShareController.php
│   └── Service/
│       ├── WishlistService.php
│       ├── PriceMonitorService.php
│       └── NotificationService.php
├── Storefront/
│   ├── Controller/
│   ├── Page/
│   └── Subscriber/
└── Administration/
    ├── module/
    └── component/
```

### Database Schema
```sql
-- Haupttabellen
wishlist (
    id BINARY(16),
    customer_id BINARY(16),
    name VARCHAR(255),
    type ENUM('private','public','shared'),
    share_token VARCHAR(64),
    created_at DATETIME,
    updated_at DATETIME
)

wishlist_item (
    id BINARY(16),
    wishlist_id BINARY(16),
    product_id BINARY(16),
    quantity INT,
    priority INT,
    note TEXT,
    added_at DATETIME,
    price_alert DECIMAL(10,2)
)

wishlist_analytics (
    id BINARY(16),
    wishlist_id BINARY(16),
    event_type VARCHAR(50),
    event_data JSON,
    created_at DATETIME
)
```

### API Endpoints
```yaml
# Store API (Frontend)
POST   /store-api/wishlist
GET    /store-api/wishlist/{id}
PUT    /store-api/wishlist/{id}
DELETE /store-api/wishlist/{id}
POST   /store-api/wishlist/{id}/item
DELETE /store-api/wishlist/{id}/item/{itemId}
POST   /store-api/wishlist/{id}/share
GET    /store-api/wishlist/shared/{token}

# Admin API
GET    /api/wishlist/analytics
GET    /api/wishlist/top-products
POST   /api/wishlist/export
```

### Performance Optimierung
- **Caching**: Redis für Wishlist-Daten
- **Queue**: RabbitMQ für Notifications
- **CDN**: Cloudflare für Share-Pages
- **Lazy Loading**: Produkt-Details on demand
- **Elasticsearch**: Für Wishlist-Suche

## 6. User Interface Design

### Frontend Components

#### Wishlist Button States
```
[ ♡ ] Zur Wunschliste     (Default)
[ ♥ ] Auf Wunschliste     (Added)
[ ⟳ ] Wird hinzugefügt... (Loading)
```

#### Wishlist Dropdown
```
┌─────────────────────────┐
│ Meine Wunschlisten   ▼  │
├─────────────────────────┤
│ ♥ Geburtstag (12)      │
│ ♥ Weihnachten (5)      │
│ ♥ Später kaufen (23)   │
├─────────────────────────┤
│ + Neue Liste erstellen  │
└─────────────────────────┘
```

#### Share Modal
```
┌─────────────────────────────────┐
│     Wunschliste teilen          │
├─────────────────────────────────┤
│ 🔗 Link kopieren                │
│ 📧 Per Email senden             │
│ 💬 WhatsApp                     │
│ 📘 Facebook                     │
│ QR Code: [████████]             │
├─────────────────────────────────┤
│ ⚙️ Datenschutz-Einstellungen    │
└─────────────────────────────────┘
```

### Admin Dashboard

#### Analytics Overview
```
┌──────────────────────────────────────┐
│        Wishlist Performance          │
├────────────┬────────────┬────────────┤
│ Total Lists│ Shared     │ Conversion │
│   12,453   │   3,201    │   23.5%    │
├────────────┴────────────┴────────────┤
│ Top Wishlist Products:              │
│ 1. Product A (523x)                 │
│ 2. Product B (421x)                 │
│ 3. Product C (399x)                 │
└──────────────────────────────────────┘
```

## 7. Go-to-Market Strategy

### Launch Plan

#### Phase 1: Beta (Monat 1)
- **Closed Beta**: 20 ausgewählte Partner-Shops
- **Feedback Loop**: Wöchentliche Calls
- **Bug Bounty**: 50€ pro kritischem Bug
- **Case Studies**: 3 Success Stories

#### Phase 2: Public Launch (Monat 2)
- **ProductHunt Launch**: Koordiniert mit Shopware Community
- **Shopware Store**: Premium Placement Deal
- **Launch Offer**: 50% Rabatt erste 3 Monate
- **Webinar Series**: "Conversion Boost mit Wishlists"

#### Phase 3: Growth (Monat 3-6)
- **Content Marketing**: SEO-optimierte Guides
- **Influencer**: Deutsche E-Commerce YouTuber
- **Partner Program**: Agenturen einbinden
- **Shopware Events**: Unite, Community Days

### Marketing Channels
1. **Shopware Store** (40% Traffic erwartet)
2. **SEO/Content** (25%)
3. **Community/Forums** (20%)
4. **Paid Ads** (10%)
5. **Referrals** (5%)

### Pricing Psychology
- **Anchoring**: Enterprise zuerst zeigen
- **Decoy Effect**: Business Plan optimiert
- **Loss Aversion**: "Noch X Tage Trial"
- **Social Proof**: Live-Counter aktive Wishlists

## 8. Success Metrics & KPIs

### Business Metrics
- **MRR Growth**: 25% MoM
- **Churn Rate**: < 5% monthly
- **LTV:CAC**: > 3:1
- **Trial-to-Paid**: > 25%
- **NPS Score**: > 50

### Product Metrics
- **Adoption Rate**: 80% der Shops nutzen Feature
- **Daily Active Wishlists**: > 60%
- **Share Rate**: > 15% der Listen
- **Wishlist-to-Cart**: > 35%
- **Items per Wishlist**: Ø 8-12

### Technical Metrics
- **Page Load**: < 100ms für Wishlist-Button
- **API Response**: < 200ms
- **Uptime**: 99.9% SLA
- **Error Rate**: < 0.1%

## 9. Roadmap & Milestones

### Q1 2024: Foundation
- ✓ MVP Development
- ✓ Beta Testing
- ✓ Shopware Store Zertifizierung
- ✓ Launch Marketing-Website

### Q2 2024: Growth
- Advanced Analytics
- B2B Features
- Mobile App (iOS/Android)
- Shopware 6.5 Compatibility

### Q3 2024: Expansion
- KI-Recommendations
- Social Commerce Integration
- International Expansion (EN, FR, ES)
- Enterprise Features

### Q4 2024: Innovation
- AR Wishlist Visualisierung
- Voice Commerce Integration
- Blockchain-basierte Geschenklisten
- Predictive Analytics

## 10. Risk Management

### Technical Risks
- **Shopware Updates**: Dediziertes Compatibility Team
- **Performance**: Horizontal Scaling vorbereitet
- **Security**: Penetration Testing quarterly

### Business Risks
- **Shopware Native Feature**: Differenzierung durch Innovation
- **Konkurrenz**: First-Mover Advantage nutzen
- **Churn**: Customer Success Team ab Tag 1

### Mitigation Strategies
- **Feature Velocity**: 2-Wochen Sprints
- **Customer Lock-in**: Daten-Export nur in Premium
- **Partnerschaften**: Exklusive Agency Deals

## 11. Anhang: Technische Spezifikationen

### Systemanforderungen
- Shopware 6.4.0+
- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+
- Redis empfohlen
- 2GB RAM minimum

### Installation
```bash
composer require advanced-wishlist/shopware6
bin/console plugin:refresh
bin/console plugin:install AdvancedWishlist
bin/console plugin:activate AdvancedWishlist
bin/console cache:clear
```

### Lizenzierung
- **License Key Validation**: Online & Offline
- **Domain Binding**: Flexible für Staging
- **Update Channel**: Stable, Beta, Dev
- **Support Period**: 12 Monate included