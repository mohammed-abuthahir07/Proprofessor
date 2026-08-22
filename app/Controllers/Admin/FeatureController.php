<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use Features;

final class FeatureController extends Controller
{
    public function index(): void
    {
        require_admin_perm('manage_features');
        $user = $this->user();
        $this->view('admin/features', [
            'title' => 'Feature Flags',
            'active' => 'features',
            'subtitle' => 'Expandable options per institution',
            'features' => Features::forInstitution((int)$user['institution_id']),
        ]);
    }

    public function toggle(): void
    {
        require_admin_perm('manage_features');
        $this->verifyCsrf();
        $user = $this->user();
        $code = (string)$this->post('feature_code');
        $enabled = (bool)$this->post('is_enabled');
        Features::toggle((int)$user['institution_id'], $code, $enabled);
        $this->flash('success', "Feature {$code} updated.");
        $this->redirect('/admin/features');
    }
}
