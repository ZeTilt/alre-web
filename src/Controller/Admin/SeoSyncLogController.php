<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Routes moved to DashboardController (requires EasyAdmin AdminContext).
 */
#[IsGranted('ROLE_ADMIN')]
class SeoSyncLogController extends AbstractController
{
}
