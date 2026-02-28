<?php

namespace App\Dashboard\Widget;

/**
 * Provides built-in widget definitions for the dashboard.
 */
final class DefaultWidgetDefinitionsProvider
{
    /** @return WidgetDefinition[] */
    public function getDefinitions(): array
    {
        return [
            new WidgetDefinition(
                id: 'kpi_products',
                title: 'Total Products',
                description: 'Number of products in catalog',
                template: 'admin/widgets/kpi_products.html.twig',
                defaultW: 2,
                defaultH: 1,
                defaultSettings: [],
                settingsSchema: [],
            ),
            new WidgetDefinition(
                id: 'kpi_orders',
                title: 'Total Orders',
                description: 'Number of orders',
                template: 'admin/widgets/kpi_orders.html.twig',
                defaultW: 2,
                defaultH: 1,
                defaultSettings: [],
                settingsSchema: [],
            ),
            new WidgetDefinition(
                id: 'kpi_users',
                title: 'Total Users',
                description: 'Number of registered users',
                template: 'admin/widgets/kpi_users.html.twig',
                defaultW: 2,
                defaultH: 1,
                defaultSettings: [],
                settingsSchema: [],
            ),
            new WidgetDefinition(
                id: 'recent_orders',
                title: 'Recent Orders',
                description: 'Latest orders list',
                template: 'admin/widgets/recent_orders.html.twig',
                defaultW: 6,
                defaultH: 2,
                defaultSettings: ['limit' => 5],
                settingsSchema: [
                    'limit' => ['type' => 'number', 'label' => 'Number of orders', 'default' => 5],
                ],
            ),
            new WidgetDefinition(
                id: 'chart_sales',
                title: 'Sales Over Time',
                description: 'Orders and revenue for the last 30 days',
                template: 'admin/widgets/chart_sales.html.twig',
                defaultW: 4,
                defaultH: 2,
                defaultSettings: ['days' => 30],
                settingsSchema: [
                    'days' => ['type' => 'number', 'label' => 'Number of days', 'default' => 30],
                ],
            ),
            new WidgetDefinition(
                id: 'chart_orders_by_status',
                title: 'Orders by Status',
                description: 'Order count by status',
                template: 'admin/widgets/chart_orders_by_status.html.twig',
                defaultW: 2,
                defaultH: 2,
                defaultSettings: [],
                settingsSchema: [],
            ),
        ];
    }
}
