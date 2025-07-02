# Advanced Wishlist System for Shopware 6

## Overview

The Advanced Wishlist System transforms Shopware 6 shops through a complete wishlist ecosystem that not only retrofits the missing core functionality but also integrates modern social commerce features and enables measurable conversion increases.

## Features

### Core Features (MVP - Version 1.0)

#### Wishlist Management
- Add products to wishlist with one click
- Create and name multiple wishlists
- Move products between lists
- Add notes to products
- Set priorities/ranking

#### Social Sharing
- Share wishlists via link (public/private)
- Generate QR code for offline sharing
- WhatsApp/Email integration
- Facebook/Instagram share buttons
- Privacy settings per list

#### Guest Wishlist
- Use wishlist without registration
- Transfer list after registration
- Cookie-based storage (GDPR-compliant)
- Email reminder for wishlist items

### Advanced Features (Version 1.1+)

#### Price Monitoring
- Set price alerts for wishlist items
- Availability notifications
- Sale alerts for saved products
- View historical price development

#### Analytics Dashboard
- Analyze top wishlist products
- Conversion rate from wishlist to purchase
- View sharing statistics
- Abandoned wishlist recovery

#### B2B Features
- Team wishlists with roles
- Approval workflows
- Budget limits per list
- CSV export/import

## Technical Information

### System Requirements
- Shopware 6.4.0+
- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+
- Redis recommended
- 2GB RAM minimum

### Installation

```bash
composer require advanced-wishlist/shopware6
bin/console plugin:refresh
bin/console plugin:install AdvancedWishlist
bin/console plugin:activate AdvancedWishlist
bin/console cache:clear
```

## Documentation

Comprehensive documentation is available in the `/docs/advancedWishlist` directory:

- [Product Requirements Document](../docs/advancedWishlist/prd-advanced-wishlist.md)
- [Development Roadmap](../docs/advancedWishlist/wishlist-roadmap.md)
- [Technical Architecture](../docs/advancedWishlist/wishlist-architecture.md)
- [Database Schema](../docs/advancedWishlist/wishlist-database-schema.md)
- [API Documentation](../docs/advancedWishlist/wishlist-store-api.md)
- [Frontend Components](../docs/advancedWishlist/wishlist-frontend-components.md)

## Pricing Plans

### 🆓 BASIC (Free)
- 1 Wishlist per customer
- Max. 50 products
- Basic Sharing (Link only)
- 30 days cookie storage for guests
- Community Support
- "Powered by" Branding

### 💎 PROFESSIONAL (49€/month)
- Unlimited Wishlists
- Unlimited Products
- All Sharing Options
- Price Drop Alerts
- Guest Wishlist (90 days)
- Email Notifications
- Basic Analytics
- Priority Email Support
- White-Label Option (+20€)

### 🏢 BUSINESS (99€/month)
- Everything from Professional
- Advanced Analytics & Reports
- A/B Testing for Wishlist Buttons
- Abandoned Wishlist Recovery
- API Access
- Multi-Language Support
- Custom Email Templates
- Live Chat Support
- 2 Developer hours/month included

### 🚀 ENTERPRISE (199€/month + Setup)
- Everything from Business
- B2B Team Features
- Multi-Shop/Clients
- Custom Integrations
- SSO/SAML Support
- Dedicated Account Manager
- SLA guaranteed
- Custom Development
- On-Premise Option

## License

This plugin is licensed under the proprietary license. See the LICENSE file for details.

## Support

For support, please contact:
- Email: support@advanced-wishlist.com
- Website: https://advanced-wishlist.com