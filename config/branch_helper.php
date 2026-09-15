<?php
/**
 * Branch scope helper.
 * Provides SQL fragments + current branch values for filtering.
 */


if (!function_exists('branchScopeSql')) {
    /**
     * Returns a SQL fragment like " AND branch_id = 3" when a specific
     * branch is selected, or "" (empty) when the user can see all branches.
     *
     * @param string $columnName  The column to filter on (defaults to 'branch_id')
     * @return string
     */
    function branchScopeSql($columnName = 'branch_id') {
        if (!isset($_SESSION['role'])) return '';

        // Admin: "all" → no filter, specific branch → filter
        if ($_SESSION['role'] === 'admin') {
            $view = $_SESSION['view_branch'] ?? 'all';
            if ($view === 'all' || $view === null || $view === '' || $view === 0) {
                return '';
            }
            return " AND {$columnName} = " . (int)$view;
        }

        // Staff / operator: locked to their own branch
        $bid = $_SESSION['branch_id'] ?? null;
        if (!$bid) return ''; // no branch assigned → no filter

        return " AND {$columnName} = " . (int)$bid;
    }
}

if (!function_exists('currentBranchId')) {
    /**
     * Returns the branch_id used for INSERT tagging.
     *  - Admin viewing "all"        → null  (record goes in unassigned)
     *  - Admin viewing a branch    → that branch
     *  - Staff / operator          → their own branch
     *  - Not assigned anywhere     → null
     *
     * @return int|null
     */
    function currentBranchId() {
        if (!isset($_SESSION['role'])) return null;

        if ($_SESSION['role'] === 'admin') {
            $view = $_SESSION['view_branch'] ?? 'all';
            return ($view === 'all' || $view === '' || $view === null || $view === 0)
                ? null
                : (int)$view;
        }

        $bid = $_SESSION['branch_id'] ?? null;
        return $bid ? (int)$bid : null;
    }
}
?>