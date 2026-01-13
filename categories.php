<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    if ($name == '') {
        $error = "Category name is required.";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO categories (company_id, name, description, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute(array($_SESSION['company_id'], $name, $description));
            $message = "Category added successfully!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $category_id = $_POST['category_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    if ($name == '') {
        $error = "Category name is required.";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ? AND company_id = ?");
            $stmt->execute(array($name, $description, $category_id, $_SESSION['company_id']));
            $message = "Category updated successfully!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

if (isset($_GET['delete'])) {
    try {
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $checkStmt->execute(array($_GET['delete']));
        $productCount = $checkStmt->fetchColumn();
        
        if ($productCount > 0) {
            $error = "Cannot delete category. It has " . $productCount . " product(s) assigned.";
        } else {
            $stmt = $conn->prepare("DELETE FROM categories WHERE id = ? AND company_id = ?");
            $stmt->execute(array($_GET['delete'], $_SESSION['company_id']));
            $message = "Category deleted successfully!";
        }
    } catch (PDOException $e) {
        $error = "Error deleting category.";
    }
}

$stmt = $conn->prepare("SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count FROM categories c WHERE c.company_id = ? ORDER BY c.name ASC");
$stmt->execute(array($_SESSION['company_id']));
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">Category Management</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="fas fa-plus me-2"></i>Add Category
        </button>
    </div>

    <?php if ($message != ''): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error != ''): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Category Name</th>
                            <th>Description</th>
                            <th>Products</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">No categories found</td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $count = 1;
                            foreach ($categories as $category): 
                            $desc = isset($category['description']) ? $category['description'] : '';
                            $productCount = isset($category['product_count']) ? $category['product_count'] : 0;
                            ?>
                            <tr>
                                <td><?php echo $count; ?></td>
                                <td><strong><?php echo htmlspecialchars($category['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($desc); ?></td>
                                <td><span class="badge bg-info"><?php echo $productCount; ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-warning edit-category-btn"
                                        data-id="<?php echo $category['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                        data-description="<?php echo htmlspecialchars($desc); ?>"
                                        data-bs-toggle="modal" data-bs-target="#editCategoryModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?delete=<?php echo $category['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this category?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php 
                            $count++;
                            endforeach; 
                            ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="add_category" value="1">
                <div class="mb-3">
                    <label class="form-label">Category Name *</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Category</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="update_category" value="1">
                <input type="hidden" name="category_id" id="edit_category_id">
                <div class="mb-3">
                    <label class="form-label">Category Name *</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Category</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var editButtons = document.querySelectorAll('.edit-category-btn');
    for (var i = 0; i < editButtons.length; i++) {
        editButtons[i].addEventListener('click', function() {
            document.getElementById('edit_category_id').value = this.getAttribute('data-id');
            document.getElementById('edit_name').value = this.getAttribute('data-name');
            document.getElementById('edit_description').value = this.getAttribute('data-description');
        });
    }
});
</script>

<?php require_once 'templates/footer.php'; ?>