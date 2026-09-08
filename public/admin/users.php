<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
cg_require_admin();
$pageTitle = 'User Management';
$activeNav = 'admin';
$pageScripts = ['admin/users.js'];
require __DIR__ . '/../../includes/partials/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h4 class="fw-800 m-0 d-none d-lg-block">User Management</h4>
  <button class="cg-btn cg-btn-primary" data-bs-toggle="modal" data-bs-target="#cgUserModal"><i class="fa-solid fa-user-plus"></i> Add User</button>
</div>

<div class="cg-card mb-3">
  <div class="row g-2">
    <div class="col-md-5"><input type="text" id="cgUserSearch" class="cg-form-control" placeholder="Search users..."></div>
    <div class="col-md-3">
      <select id="cgUserRoleFilter" class="cg-form-select">
        <option value="">All Roles</option>
        <option value="super_admin">Super Admin</option><option value="administrator">Administrator</option>
        <option value="investigator">Investigator</option><option value="analyst">Analyst</option><option value="viewer">Viewer</option>
      </select>
    </div>
    <div class="col-md-3">
      <select id="cgUserStatusFilter" class="cg-form-select"><option value="">All Status</option><option value="1">Active</option><option value="0">Disabled</option></select>
    </div>
  </div>
</div>

<div class="cg-card p-0 overflow-hidden">
  <table class="cg-table mb-0">
    <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Department</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
    <tbody id="cgUsersTableBody"></tbody>
  </table>
  <div id="cgUsersEmpty" class="cg-empty-state" hidden><i class="fa-solid fa-users"></i>No users found.</div>
</div>

<div class="modal fade" id="cgUserModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="background:var(--cg-panel);border:1px solid var(--cg-border-soft);color:var(--cg-text)">
      <div class="modal-header border-0"><h5 class="modal-title fw-700">Add User</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
      <form id="cgUserForm">
        <div class="modal-body row g-3">
          <div class="col-md-6"><label class="cg-label">Full Name</label><input required name="full_name" class="cg-form-control"></div>
          <div class="col-md-6"><label class="cg-label">Username</label><input required name="username" class="cg-form-control"></div>
          <div class="col-md-6"><label class="cg-label">Email</label><input required type="email" name="email" class="cg-form-control"></div>
          <div class="col-md-6"><label class="cg-label">Phone</label><input name="phone" class="cg-form-control"></div>
          <div class="col-md-6"><label class="cg-label">Department</label><input name="department" class="cg-form-control"></div>
          <div class="col-md-6">
            <label class="cg-label">Role</label>
            <select name="role" class="cg-form-select">
              <option value="investigator">Investigator</option><option value="analyst">Analyst</option>
              <option value="viewer">Viewer</option><option value="administrator">Administrator</option><option value="super_admin">Super Admin</option>
            </select>
          </div>
          <div class="col-md-6"><label class="cg-label">Temporary Password</label><input required type="text" name="password" class="cg-form-control" minlength="8"></div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="cg-btn cg-btn-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="cg-btn cg-btn-primary">Create User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/partials/footer.php'; ?>
