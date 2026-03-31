<?php
/**
 * Gestion Utilisateurs – Réservé Admin
 */
requireRole('admin');
$pdo = Database::getInstance();

// ── Actions POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'create':
            case 'update':
                $nom    = trim($_POST['nom']    ?? '');
                $prenom = trim($_POST['prenom'] ?? '');
                $login  = trim($_POST['login']  ?? '');
                $role   = $_POST['role']        ?? 'percepteur';

                if (!$nom || !$prenom || !$login) jsonError('Tous les champs obligatoires.');
                if (!in_array($role, ['admin','comptable','percepteur'], true)) jsonError('Rôle invalide.');

                if ($action === 'create') {
                    $pass = trim($_POST['password'] ?? '');
                    if (strlen($pass) < 8) jsonError('Mot de passe minimum 8 caractères.');
                    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
                    $stmt = $pdo->prepare("
                        INSERT INTO utilisateurs (nom, prenom, login, password, role, whodone)
                        VALUES (:nom, :prenom, :login, :hash, :role, :whodone)
                    ");
                    $stmt->execute([':nom'=>$nom,':prenom'=>$prenom,':login'=>$login,
                                    ':hash'=>$hash,':role'=>$role,':whodone'=>Session::getUserId()]);
                    jsonSuccess('Utilisateur créé avec succès.', ['id' => $pdo->lastInsertId()]);
                } else {
                    $id = (int)($_POST['id'] ?? 0);
                    if (!$id) jsonError('ID manquant.');
                    $params = [':nom'=>$nom,':prenom'=>$prenom,':login'=>$login,
                               ':role'=>$role,':whodone'=>Session::getUserId(),':id'=>$id];
                    $sql = "UPDATE utilisateurs SET nom=:nom, prenom=:prenom, login=:login,
                            role=:role, whodone=:whodone WHERE id=:id AND isDeleted=0";
                    if (!empty($_POST['password'])) {
                        $pass = trim($_POST['password']);
                        if (strlen($pass) < 8) jsonError('Mot de passe minimum 8 caractères.');
                        $params[':hash'] = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
                        $sql = "UPDATE utilisateurs SET nom=:nom, prenom=:prenom, login=:login,
                                role=:role, password=:hash, whodone=:whodone WHERE id=:id AND isDeleted=0";
                    }
                    $pdo->prepare($sql)->execute($params);
                    jsonSuccess('Utilisateur mis à jour.');
                }
                break;

            case 'toggle_actif':
                $id = (int)($_POST['id'] ?? 0);
                if (!$id) jsonError('ID manquant.');
                if ($id === Session::getUserId()) jsonError('Impossible de suspendre votre propre compte.');
                $pdo->prepare("UPDATE utilisateurs SET est_actif = 1 - est_actif WHERE id=:id AND isDeleted=0")
                    ->execute([':id' => $id]);
                jsonSuccess('Statut mis à jour.');
                break;

            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                if (!$id) jsonError('ID manquant.');
                if ($id === Session::getUserId()) jsonError('Impossible de supprimer votre propre compte.');
                $pdo->prepare("UPDATE utilisateurs SET isDeleted=1, whodone=:who WHERE id=:id")
                    ->execute([':who' => Session::getUserId(), ':id' => $id]);
                jsonSuccess('Utilisateur supprimé (archivé).');
                break;

            default:
                jsonError('Action inconnue.');
        }
    } catch (PDOException $e) {
        jsonError('Erreur BDD : ' . (APP_ENV === 'development' ? $e->getMessage() : 'Contactez l\'admin.'));
    }
}

// ── Liste utilisateurs ────────────────────────────────────────────────────────
$users = $pdo->query("
    SELECT id, nom, prenom, login, role, est_actif, whendone
    FROM utilisateurs
    WHERE isDeleted = 0
    ORDER BY role, nom
")->fetchAll();

$pageTitle = 'Gestion des Utilisateurs';
include ROOT_PATH . '/templates/layouts/header.php';
?>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-csi-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-people me-2"></i>Gestion des Utilisateurs</h5>
                <button class="btn text-white btn-sm" style="background:var(--csi-green);"
                        data-bs-toggle="modal" data-bs-target="#modalUser" onclick="openCreateModal()">
                    <i class="bi bi-plus-circle me-1"></i>Nouvel utilisateur
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tblUsers" class="table table-hover align-middle" data-datatable>
                        <thead class="table-light">
                            <tr>
                                <th>#</th><th>Nom complet</th><th>Login</th><th>Rôle</th>
                                <th>Statut</th><th>Créé le</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= (int)$u['id'] ?></td>
                                <td><?= h($u['nom'] . ' ' . $u['prenom']) ?></td>
                                <td><code><?= h($u['login']) ?></code></td>
                                <td>
                                    <?php
                                    $rc = match($u['role']){
                                        'admin'     => 'danger',
                                        'comptable' => 'warning',
                                        default     => 'info'
                                    };
                                    $rl = match($u['role']){
                                        'admin'     => 'Administrateur',
                                        'comptable' => 'Comptable',
                                        default     => 'Percepteur'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $rc ?>"><?= $rl ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $u['est_actif'] ? 'success' : 'secondary' ?>">
                                        <?= $u['est_actif'] ? 'Actif' : 'Suspendu' ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?= formatDate($u['whendone'], 'd/m/Y') ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" title="Modifier"
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-<?= $u['est_actif'] ? 'warning' : 'success' ?> me-1"
                                            title="<?= $u['est_actif'] ? 'Suspendre' : 'Activer' ?>"
                                            onclick="toggleActif(<?= (int)$u['id'] ?>, '<?= h($u['nom']) ?>')">
                                        <i class="bi bi-<?= $u['est_actif'] ? 'pause-circle' : 'play-circle' ?>"></i>
                                    </button>
                                    <?php if ($u['id'] != Session::getUserId()): ?>
                                    <button class="btn btn-sm btn-outline-danger" title="Supprimer"
                                            onclick="deleteUser(<?= (int)$u['id'] ?>, '<?= h($u['nom']) ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal Utilisateur ───────────────────────────────────────────────────── -->
<div class="modal fade" id="modalUser" tabindex="-1" aria-labelledby="modalUserLabel">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalUserLabel"><i class="bi bi-person-plus me-2"></i>Utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formUser" novalidate>
                    <input type="hidden" name="action" id="fAction" value="create">
                    <input type="hidden" name="id"     id="fId"     value="">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nom" id="fNom" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Prénom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="prenom" id="fPrenom" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Login <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="login" id="fLogin" required autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Rôle <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" id="fRole" required>
                                <option value="percepteur">Percepteur</option>
                                <option value="comptable">Comptable</option>
                                <option value="admin">Administrateur</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" id="passLabel">Mot de passe <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="password" id="fPassword"
                                   autocomplete="new-password" minlength="8"
                                   placeholder="Minimum 8 caractères">
                            <div class="form-text" id="passHint"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn text-white" style="background:var(--csi-green);"
                        onclick="saveUser()">
                    <i class="bi bi-save me-1"></i>Enregistrer
                </button>
            </div>
        </div>
    </div>
</div>

<?php $usersUrl = url('index.php?page=utilisateurs'); $extraJs = <<<HEREDOC
<script>
const USERS_URL = '{$usersUrl}';

function openCreateModal() {
    document.getElementById('modalUserLabel').innerHTML = '<i class="bi bi-person-plus me-2"></i>Nouvel utilisateur';
    document.getElementById('fAction').value = 'create';
    document.getElementById('fId').value     = '';
    document.getElementById('formUser').reset();
    document.getElementById('passLabel').innerHTML = 'Mot de passe <span class="text-danger">*</span>';
    document.getElementById('passHint').textContent = '';
    document.getElementById('fPassword').required = true;
}

function openEditModal(u) {
    document.getElementById('modalUserLabel').innerHTML = '<i class="bi bi-pencil me-2"></i>Modifier utilisateur';
    document.getElementById('fAction').value  = 'update';
    document.getElementById('fId').value      = u.id;
    document.getElementById('fNom').value     = u.nom;
    document.getElementById('fPrenom').value  = u.prenom;
    document.getElementById('fLogin').value   = u.login;
    document.getElementById('fRole').value    = u.role;
    document.getElementById('fPassword').value= '';
    document.getElementById('fPassword').required = false;
    document.getElementById('passLabel').innerHTML = 'Nouveau mot de passe <small class="text-muted">(laisser vide = inchangé)</small>';
    document.getElementById('passHint').textContent = '';
    new bootstrap.Modal(document.getElementById('modalUser')).show();
}

function saveUser() {
    const form = document.getElementById('formUser');
    const data = Object.fromEntries(new FormData(form));
    if (!data.nom || !data.prenom || !data.login) { showToast('warning', 'Champs obligatoires manquants.'); return; }
    ajaxPost(USERS_URL, data, () => { bootstrap.Modal.getOrCreateInstance(document.getElementById('modalUser')).hide(); setTimeout(() => location.reload(), 800); });
}

function toggleActif(id, nom) {
    if (!confirm('Changer le statut de ' + nom + ' ?')) return;
    ajaxPost(USERS_URL, { action: 'toggle_actif', id }, () => setTimeout(() => location.reload(), 800));
}

function deleteUser(id, nom) {
    if (!confirm('Supprimer (archiver) l\'utilisateur ' + nom + ' ?')) return;
    ajaxPost(USERS_URL, { action: 'delete', id }, () => setTimeout(() => location.reload(), 800));
}
</script>
HEREDOC;
include ROOT_PATH . '/templates/layouts/footer.php';
?>
