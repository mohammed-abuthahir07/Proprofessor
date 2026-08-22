<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Institution;
use Database;

final class BillingController extends Controller
{
    public function index(): void
    {
        require_admin_perm('manage_billing');
        $user = $this->user();
        $inst = Institution::find((int)$user['institution_id']);
        $seatsUsed = (int)(Database::fetch(
            'SELECT COUNT(*) c FROM users WHERE institution_id = ? AND role IN ("professor","hod","admin")',
            [$user['institution_id']]
        )['c'] ?? 0);

        $this->view('admin/billing', [
            'title' => 'Subscription & Billing',
            'active' => 'billing',
            'inst' => $inst,
            'seatsUsed' => $seatsUsed,
        ]);
    }
}
