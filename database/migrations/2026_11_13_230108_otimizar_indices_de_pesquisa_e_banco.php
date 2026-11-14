<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona índices estratégicos nas tabelas centrais do CRM, pedidos, atendimentos e
 * Google Ads para acelerar pesquisas em tempo real e evitar travamentos no MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Leads
        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                if (! Schema::hasIndex('leads', 'leads_owner_id_index')) {
                    $table->index('owner_id', 'leads_owner_id_index');
                }
                if (! Schema::hasIndex('leads', 'leads_phone_index')) {
                    $table->index('phone', 'leads_phone_index');
                }
                if (! Schema::hasIndex('leads', 'leads_email_index')) {
                    $table->index('email', 'leads_email_index');
                }
                if (! Schema::hasIndex('leads', 'leads_status_created_idx')) {
                    $table->index(['status', 'created_at'], 'leads_status_created_idx');
                }
            });
        }

        // 2. Clientes (CRM)
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (! Schema::hasIndex('customers', 'customers_documento_index')) {
                    $table->index('documento', 'customers_documento_index');
                }
                if (! Schema::hasIndex('customers', 'customers_telefone_index')) {
                    $table->index('telefone', 'customers_telefone_index');
                }
                if (! Schema::hasIndex('customers', 'customers_email_index')) {
                    $table->index('email', 'customers_email_index');
                }
                if (! Schema::hasIndex('customers', 'customers_owner_id_index')) {
                    $table->index('owner_id', 'customers_owner_id_index');
                }
                if (! Schema::hasIndex('customers', 'customers_status_created_idx')) {
                    $table->index(['status', 'created_at'], 'customers_status_created_idx');
                }
            });
        }

        // 3. Pedidos (Store Orders)
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasIndex('orders', 'orders_customer_id_index')) {
                    $table->index('customer_id', 'orders_customer_id_index');
                }
                if (! Schema::hasIndex('orders', 'orders_user_id_index')) {
                    $table->index('user_id', 'orders_user_id_index');
                }
                if (! Schema::hasIndex('orders', 'orders_telefone_index')) {
                    $table->index('telefone', 'orders_telefone_index');
                }
                if (! Schema::hasIndex('orders', 'orders_email_index')) {
                    $table->index('email', 'orders_email_index');
                }
            });
        }

        // 4. Interações CRM
        if (Schema::hasTable('interactions')) {
            Schema::table('interactions', function (Blueprint $table) {
                if (! Schema::hasIndex('interactions', 'interactions_tipo_index')) {
                    $table->index('tipo', 'interactions_tipo_index');
                }
                if (! Schema::hasIndex('interactions', 'interactions_concluida_index')) {
                    $table->index('concluida', 'interactions_concluida_index');
                }
                if (! Schema::hasIndex('interactions', 'interactions_proxima_acao_index')) {
                    $table->index('proxima_acao_em', 'interactions_proxima_acao_index');
                }
            });
        }

        // 5. Contas e Planos do Google Ads
        if (Schema::hasTable('google_ads_accounts')) {
            Schema::table('google_ads_accounts', function (Blueprint $table) {
                if (! Schema::hasIndex('google_ads_accounts', 'google_ads_accounts_status_idx')) {
                    $table->index('status', 'google_ads_accounts_status_idx');
                }
                if (! Schema::hasIndex('google_ads_accounts', 'google_ads_accounts_customer_id_idx')) {
                    $table->index('customer_id', 'google_ads_accounts_customer_id_idx');
                }
            });
        }

        if (Schema::hasTable('google_ads_planos')) {
            Schema::table('google_ads_planos', function (Blueprint $table) {
                if (! Schema::hasIndex('google_ads_planos', 'google_ads_planos_conta_status_idx')) {
                    $table->index(['google_ads_account_id', 'status'], 'google_ads_planos_conta_status_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropIndex('leads_owner_id_index');
                $table->dropIndex('leads_phone_index');
                $table->dropIndex('leads_email_index');
                $table->dropIndex('leads_status_created_idx');
            });
        }

        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropIndex('customers_documento_index');
                $table->dropIndex('customers_telefone_index');
                $table->dropIndex('customers_email_index');
                $table->dropIndex('customers_owner_id_index');
                $table->dropIndex('customers_status_created_idx');
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex('orders_customer_id_index');
                $table->dropIndex('orders_user_id_index');
                $table->dropIndex('orders_telefone_index');
                $table->dropIndex('orders_email_index');
            });
        }

        if (Schema::hasTable('interactions')) {
            Schema::table('interactions', function (Blueprint $table) {
                $table->dropIndex('interactions_tipo_index');
                $table->dropIndex('interactions_concluida_index');
                $table->dropIndex('interactions_proxima_acao_index');
            });
        }

        if (Schema::hasTable('google_ads_accounts')) {
            Schema::table('google_ads_accounts', function (Blueprint $table) {
                $table->dropIndex('google_ads_accounts_status_idx');
                $table->dropIndex('google_ads_accounts_customer_id_idx');
            });
        }

        if (Schema::hasTable('google_ads_planos')) {
            Schema::table('google_ads_planos', function (Blueprint $table) {
                $table->dropIndex('google_ads_planos_conta_status_idx');
            });
        }
    }
};
