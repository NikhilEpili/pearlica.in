# Pearlica.in - Complete Feature README

This document lists the implemented features available on:

- The customer-facing website (storefront)
- The admin panel

It is written from the current codebase behavior.

---

## 1) Website Features (Storefront)

## 1.1 Core Storefront Structure

- Clean URL routing without `.php` in URLs via rewrite rules
- Custom `404` page support
- Shared global navigation and footer across pages
- Desktop + mobile responsive navigation patterns
- Mobile bottom dock with quick actions
- Dynamic category links in navigation from catalog data

## 1.2 Home Page (`/index`)

- Full-width hero video section
- "New Arrivals" rail powered by recent product logic
- "Shop by Category" sections generated from parent categories
- Category-level "View all" linking into filtered product listing
- Editorial content section ("The Collection")
- Customer testimonials carousel
- Launch-offer component integration

## 1.3 Catalog Browsing (`/products`)

- Paginated product listing
- Filter modal with:
  - Category (multi-select)
  - Shop for (All / Women / Men)
  - Text search (`q`)
  - Min / max price + range sliders
  - 925 silver filter
  - On sale filter
  - In-stock filter
  - Sort (Featured / Newest / Price asc / Price desc)
- Filter state persistence in query parameters
- Clear all filters action
- Grid density toggle (4-column / 2-column)
- Empty-state guidance when no matching results
- Pagination with filter-aware URLs

## 1.4 Gifting Collection (`/gifting`)

- Dedicated gifting page based on `is_gifting` product flag
- Search within gifting items
- Sorting (Featured / Newest / Price asc / Price desc)
- Grid density toggle
- Wishlist + cart actions on gifting cards
- Paginated gifting listing

## 1.5 Product Detail Page (`/product?slug=...`)

- Product lookup by slug with active-product gating
- Product gallery carousel with:
  - Multiple images
  - Dot navigation
  - Full-screen/gallery button
- Variant switching via product groups ("Other variants")
- Pricing display with discount + original MRP
- Tax-included note rendering
- Product sharing action
- Wishlist toggle from PDP
- SKU display (if set)
- Dynamic product specs grid
- Trust badges strip (secure payment, 925 certified, etc.)
- Add to cart + Buy now CTA
- Mobile sticky CTA bar
- Product detail accordions:
  - Description
  - Specification
  - Additional information
  - Styling tips
- Related products ("You may also like") carousel/cards

## 1.6 Wishlist

- Cookie-based wishlist persistence
- Add/remove wishlist from listing cards and PDP
- Wishlist page (`/wishlist`) showing saved products
- "Move to cart" flow from wishlist
- Wishlist-to-cart + remove actions
- Optional JSON response mode in wishlist toggle endpoint

## 1.7 Cart System

- Cookie-based cart storage
- Add-to-cart from:
  - Product detail page
  - Related products
  - Wishlist move-to-cart
- Quantity update support
- Item remove support
- AJAX cart update with recalculated line totals + total amount
- Cart drawer component with:
  - Line item controls
  - Quantity steppers
  - Remove links
  - Live total
  - Checkout action
- Redirect behavior for empty/non-empty cart states

## 1.8 Checkout Experience (`/checkout`)

- Login-required checkout
- Non-empty-cart enforcement
- Full order summary with:
  - Item-level pricing
  - MRP vs discounted pricing
  - Quantity editing and remove
  - Subtotal and savings
- Delivery details from profile data
- Link to edit profile directly from checkout
- PIN-based delivery estimate checker (Shiprocket-backed)
- Promo code system:
  - Apply promo
  - Remove promo
  - One active promo per cart
  - Session persistence + revalidation
- Gift packaging add-on toggle
- Computed order total with discounts and add-ons
- Razorpay-only payment mode with readiness checks
- Checkout CSRF protection and structured request validation

## 1.9 Payments + Post-Payment

- Razorpay order creation flow (`create-order`)
- Frontend payment execution (Razorpay Checkout)
- Server-side signature verification (`verify`)
- Idempotent payment update handling
- Order status transition to paid
- Cart clearing on successful payment
- Order success page (`/order-success?order_id=...`)
- Razorpay webhook endpoint present (`payment/razorpay/webhook.php`)

## 1.10 Shipping Integration in Order Flow

- Automatic Shiprocket sync attempt after successful payment
- Graceful failure logging without breaking payment completion

## 1.11 Customer Authentication (Storefront)

- Email OTP login (passwordless)
- Two-step flow:
  - Request OTP
  - Verify OTP
- OTP request rate limiting
- OTP expiry handling and countdown UI
- Session-based CSRF protection for auth forms
- Auth cookie set/clear logic
- Logout endpoint with safe return URL handling

## 1.12 Profile + Account

- Login-protected profile page (`/profile`)
- Editable personal fields:
  - Full name
  - Phone
  - Birthday
  - Gender
- Editable address fields:
  - Address line 1/2
  - City
  - State (Indian states dropdown)
  - Pincode
- Email shown as read-only immutable field
- CSRF-protected profile updates

## 1.13 Order History (Customer Side)

- My orders listing (`/orders`) for logged-in customer only
- Pagination on order list
- Order detail page (`/order?id=...`) ownership-restricted
- Itemized order breakdown:
  - Product lines
  - Qty
  - Subtotal/shipping/discount/total
- Payment method/status display
- Shipping destination block

## 1.14 Product Reviews (Verified Buyer Model)

- Review visibility gated to signed-in users
- Review submit allowed only when:
  - User is logged in
  - User has purchased that product
  - User has not already reviewed that product
- Star rating + optional text body
- CSRF-protected review submission
- Duplicate review prevention per user/product
- Review average + count display
- Approved review list rendering on PDP

## 1.15 Search

- Navbar search UI on desktop + mobile
- Suggest API endpoint (`/search-suggest`)
- Product/category search suggestions JSON

## 1.16 Contact + Customer Support

- Contact page with form + business details
- Form fields:
  - Name, email, phone, order ID, subject, message
- Subject whitelist validation
- CSRF protection
- Persistence of contact message in database
- Admin email notification for contact submissions
- Flash success/error handling

## 1.17 Informational Pages and Policies

- About page with:
  - Brand story
  - Founder section
  - Values grid
  - FAQ section
  - Contact CTA
- Policy pages:
  - `policies/privacy-policy`
  - `policies/terms-conditions`
  - `policies/shipping-policy`
  - `policies/return-exchange-policy`
  - `policies/care-guide`
  - `policies/pearlica-care`

## 1.18 Footer / Utility UX

- Social links (Instagram, Facebook)
- Customer care shortcuts (orders/contact/care links)
- Policy quick links
- Contact info block
- Cookie consent banner (necessary cookies acceptance)
- Tab-visibility title change ("Come Back" behavior)

## 1.19 Security Patterns in Storefront

- CSRF protection on sensitive POST actions
- Session hardening flags (`httponly`, strict mode usage)
- Auth + checkout guards for protected pages/actions
- Input sanitization + validation patterns across forms
- External redirect prevention on return URLs

---

## 2) Admin Panel Features

## 2.1 Admin Access and Session Model

- Dedicated admin login (`/admin/login.php`)
- Username/password authentication with hashed password verification
- Admin session bootstrap and logout
- Session regeneration on login
- CSRF protection for mutating admin actions
- Permission gate middleware:
  - `require_admin_login`
  - `require_permission(area)`
  - `require_super_admin`

## 2.2 Role and Permission System

- Super admin role with full access
- Non-super admin role with page-level permissions
- Permission buckets used in app:
  - dashboard
  - categories
  - product_groups
  - products
  - orders
- Access denied page
- Last-super-admin safety guard when changing roles

## 2.3 Dashboard

- Catalog summary cards with counts:
  - Products
  - Categories
  - Product groups
- Quick links into each area

## 2.4 Catalog Management - Categories

- Category listing with:
  - Parent category mapping
  - Product count
- Add category
- Edit category
- Parent-child category hierarchy support
- Auto slug generation from name
- Slug uniqueness checks
- Delete category with safety rule:
  - Cannot delete category containing products

## 2.5 Catalog Management - Product Groups (Variants)

- Product group listing with product counts
- Add/edit/delete product groups
- Slug management + uniqueness checks
- Product unassignment behavior when deleting group
- Used by storefront variant switching logic

## 2.6 Catalog Management - Products

- Product list with:
  - Pagination
  - Search by name/SKU
  - Category filter
  - Status filter
- Product add flow with fields for:
  - Basic info (name, slug, category, status, group, SKU)
  - Content (description/specification/additional info/styling tips)
  - Attributes (metal, purity, size, weight)
  - Commercial values (cost, MRP, discount, GST, stock)
  - Gifting flag
  - SEO metadata (meta title/description/keywords)
- Product status states:
  - draft
  - active
  - out_of_stock
  - discontinued
  - archived
- Product edit with same complete field coverage
- Product delete flow with confirmation

## 2.7 Product Media Management

- Multi-image upload for products
- Drag/drop image upload UI
- Client-side crop workflow (4:5, target 1080x1350)
- Skip or crop-and-upload path
- Image ordering via drag handles (SortableJS)
- Server sync of image order
- Primary image indicator management
- Image deletion action

## 2.8 Product Review Moderation (Admin Side)

- View all reviews tied to a product
- Differentiate verified-buyer reviews vs admin-added reviews
- Edit review fields:
  - Display name
  - Rating
  - Body
  - Approved/visible flag
- Add new review directly from admin
- Delete review permanently

## 2.9 SKU Assets

- SKU-based barcode generation (CODE 128)
- SKU-based QR code generation
- Preview + download in PNG and SVG formats

## 2.10 Orders Management (Admin Side)

- Orders listing with filters:
  - Order status
  - Payment method
- Pagination support
- Rich table columns:
  - Order number
  - Order status badge
  - Payment method badge
  - Shiprocket status badge
  - Customer identity
  - Total and timestamps
- Defensive behavior when orders/shiprocket tables are missing

## 2.11 Order Detail + Operations

- Full itemized order view
- Financial breakdown (subtotal, shipping, discount, total)
- Customer + shipping details
- Payment details including provider identifiers
- Status update action with allowed transitions set
- Shiprocket sync action per order
- Shiprocket details display when available:
  - Shiprocket order id
  - Shipment id
  - AWB code
  - Sync status

## 2.12 Promo Code Management

- Promo code listing with:
  - Type (percent/fixed)
  - Value
  - Min order
  - Validity windows
  - Usage count/limit
  - Active state
- Add promo code
- Edit promo code
- Delete promo code
- Validation constraints:
  - Unique code
  - Percent bounds (0-100)
  - Non-negative fixed amount

## 2.13 Admin User Management

- Admin users list (super-admin only)
- Add admin user with password creation
- Page-access checkbox permissions at creation
- Promote/demote super admin
- Change admin password
- Delete admin users (self-delete blocked)

## 2.14 Customer Directory (Store Users)

- Super-admin customer index from `store_users`
- Searchable by:
  - Email
  - Name
  - Phone
  - City/state/pincode
- Paginated customer list
- Shows profile/address metadata collected from storefront

## 2.15 Integration Settings - Razorpay

- Mode switch (test/live)
- Key configuration:
  - Test key id/secret
  - Live key id/secret
  - Webhook secret
- Currency + API base configuration
- Masked secret display
- Active key/mode visibility
- Linked Razorpay payments reporting page

## 2.16 Integration Reporting - Razorpay Payments

- Last payments table from `payments` for provider `razorpay`
- Status badge mapping
- Amount/currency display
- Provider order/payment ids
- Link to related local order detail

## 2.17 Integration Settings - Shiprocket

- Enable/disable Shiprocket integration
- Environment toggle (staging/production)
- Credentials storage (email/password)
- Pickup location + pickup PIN
- ETA estimate weight config
- Channel id + token TTL + API base overrides
- Token reset on settings save

## 2.18 Integration Reporting - Shiprocket Orders

- Shiprocket order mapping list (`shiprocket_orders`)
- Linked local order reference
- Email/payment context
- Shiprocket status and IDs (order/shipment/AWB)

---

## 3) Cross-System Integrations and Automation

- Razorpay -> payment verification -> order paid transition
- Post-payment email notifications to admin
- Post-payment Shiprocket sync attempt
- Contact form -> admin email notification
- Admin login -> admin login notification email

---

## 4) Important Functional Notes

- Cart and wishlist are cookie-based (not user-account cart tables).
- Storefront login is OTP-based (email) and passwordless.
- Reviews enforce verified-purchase checks before posting.
- Checkout requires authentication and profile-backed delivery details.
- Admin access is permission-gated; super admin unlocks integration and user modules.

---

## 5) Related Documentation

- Shiprocket integration guide: `README_SHIPROCKET.md`
