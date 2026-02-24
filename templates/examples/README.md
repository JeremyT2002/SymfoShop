# SymfoShop Frontend Examples

This directory contains comprehensive examples demonstrating the modern frontend UI system built for SymfoShop. These examples showcase reusable components, responsive design patterns, and accessibility features.

## Overview

The frontend system is built with:
- **TailwindCSS** for utility-first styling
- **Alpine.js** for lightweight JavaScript interactions
- **Font Awesome** for icons
- **Modern responsive design** with mobile-first approach
- **Accessibility standards** (WCAG 2.1, ARIA attributes)
- **Component-based architecture** for reusability

## Components

### Core Components
- `components/button.html.twig` - Flexible button component with variants, sizes, and states
- `components/card.html.twig` - Container component with hover effects and shadows
- `components/form/input.html.twig` - Form input with validation states and accessibility
- `components/alert.html.twig` - Notification component with dismissible functionality
- `components/modal.html.twig` - Modal dialog with focus management and accessibility

### Base Layout
- `base-modern.html.twig` - Modern base template with navigation, accessibility features, and responsive design

## Example Pages

### 1. Dashboard (`dashboard.html.twig`)
**Purpose**: Administrative dashboard with metrics, charts, and data tables
**Features**:
- KPI cards with trend indicators
- Chart placeholders (ready for Chart.js integration)
- Data tables with sorting and bulk actions
- Responsive sidebar navigation
- User menu with dropdown

### 2. Form Example (`form.html.twig`)
**Purpose**: Comprehensive form demonstration with validation
**Features**:
- Various input types (text, email, password, select, textarea, file)
- Form validation states and error handling
- Multi-section forms with progress indicators
- File upload with drag-and-drop
- Form groups and fieldsets

### 3. Catalog (`catalog.html.twig`)
**Purpose**: Product catalog with filtering, sorting, and grid layout
**Features**:
- Responsive product grid
- Advanced filtering sidebar
- Search functionality
- Sorting options
- Product cards with hover effects
- Pagination
- Mobile-responsive filters modal

### 4. Checkout (`checkout.html.twig`)
**Purpose**: Complete checkout flow with multi-step process
**Features**:
- Progress indicator
- Shipping and billing forms
- Payment method selection
- Order summary sidebar
- Form validation
- Security notices
- Responsive layout

### 5. Admin Dashboard (`admin.html.twig`)
**Purpose**: Administrative interface with management tools
**Features**:
- Admin sidebar navigation
- Metrics dashboard
- Data tables with actions
- Bulk operations
- Quick action cards
- User management interface

## Usage

### Including Components
```twig
{# Include a button #}
{% include 'components/button.html.twig' with {
    variant: 'primary',
    content: 'Click me',
    icon: 'fa-plus'
} %}

{# Include a card #}
{% include 'components/card.html.twig' with {
    hover: true,
    class: 'p-6'
} %}
    <p>Card content here</p>
{% endinclude %}
```

### Component Parameters

#### Button Component
- `variant`: `primary`, `secondary`, `ghost`, `danger`
- `size`: `sm`, `md`, `lg`
- `content`: Button text
- `icon`: Font Awesome icon class
- `loading`: Boolean for loading state
- `disabled`: Boolean for disabled state
- `attributes`: Additional HTML attributes

#### Card Component
- `hover`: Boolean for hover effects
- `shadow`: Shadow size (`sm`, `md`, `lg`, `xl`)
- `padding`: Padding class (`p-4`, `p-6`, etc.)
- `class`: Additional CSS classes

#### Form Input Component
- `label`: Input label text
- `name`: Input name attribute
- `type`: Input type (`text`, `email`, `password`, etc.)
- `required`: Boolean for required field
- `placeholder`: Placeholder text
- `value`: Default value
- `error`: Error message
- `help`: Help text
- `icon`: Icon for input field

#### Modal Component
- `id`: Modal ID for JavaScript targeting
- `title`: Modal title
- `size`: Modal size (`sm`, `md`, `lg`, `xl`)

## Design Principles

### Accessibility
- ARIA attributes for screen readers
- Keyboard navigation support
- Focus management in modals
- Color contrast ratios (WCAG AA compliant)
- Semantic HTML structure

### Responsive Design
- Mobile-first approach
- Breakpoint system (sm, md, lg, xl)
- Flexible grid layouts
- Touch-friendly interactions

### Performance
- Minimal CSS with utility classes
- Optimized component structure
- Efficient JavaScript interactions
- Fast loading with minimal dependencies

### User Experience
- Consistent visual hierarchy
- Generous spacing and padding
- Smooth transitions and animations
- Clear feedback for interactions
- Intuitive navigation patterns

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## Integration

To integrate these components into your Symfony application:

1. **Copy components** to your `templates/components/` directory
2. **Update base template** to extend `base-modern.html.twig`
3. **Include TailwindCSS** and Alpine.js in your asset pipeline
4. **Customize colors** in your Tailwind config to match your brand

## Customization

### Colors
The design uses a primary color palette. Update these in your Tailwind config:
```javascript
theme: {
  extend: {
    colors: {
      primary: {
        50: '#eff6ff',
        100: '#dbeafe',
        // ... more shades
        600: '#2563eb',
        700: '#1d4ed8',
      }
    }
  }
}
```

### Typography
Font sizes and weights follow a consistent scale for optimal readability.

### Spacing
Uses a standardized spacing scale (0.25rem increments) for consistency.

## Contributing

When adding new components:
1. Follow the existing naming conventions
2. Include accessibility features
3. Test responsive behavior
4. Document parameters and usage
5. Add examples in the examples directory

## License

These components are part of the SymfoShop project and follow the same licensing terms.