<?php
/**
 * UnitHR - Generic Table CRUD Module Engine
 * Provides list / add / edit / soft-delete (omitted) / export for any
 * prefixed table. Modules pass a configuration array; complex modules
 * (employeesmain, empleaves, depstructure, ...) are written custom.
 */

require_once __DIR__ . '/helpers.php';

function is_audit_col($name) {
    $auditCols = ['id','rguid','dtadded','addedbyid','addedby','addedbyname','addedbynamear',
                  'lastedit','editedbyid','editedby','editedbyname','editedbynamear','omitted'];
    return in_array($name, $auditCols, true);
}

// field_input() is now defined in helpers.php

function run_table_module($cfg) {
    $table  = $cfg['table'];
    $module = $cfg['module'] ?? $table;
    $pk     = $cfg['pk'] ?? 'id';
    $title  = $cfg['title'];
    $titleAr= $cfg['title_ar'] ?? $title;
    $allFields = $cfg['fields'];
    $fields = fields_for_lang($allFields);
    $orderBy= $cfg['order_by'] ?? "$pk DESC";
    $searchFields = $cfg['search_fields'] ?? [];
    $extraSelect = $cfg['extra_select'] ?? '';
    $extraFrom   = $cfg['extra_from'] ?? '';
    $extraWhere  = $cfg['extra_where'] ?? '';
    $listCols    = $cfg['list_cols'] ?? [];

    require_login();
    $canAdd = user_can($module, 'add');
    $canEdit = user_can($module, 'edit');
    $canDel = user_can($module, 'del');

    $action = $_GET['a'] ?? 'list';
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $search = trim($_GET['search'] ?? '');
    $u = current_user();

    // ---------- EXPORT ----------
    if ($action === 'export') {
        require_permission($module, 'view');
        $fmt = $_GET['fmt'] ?? 'csv';
        if ($listCols) {
            $cols = $listCols;
        } else {
            $cols = array_column($fields, 'name');
            $cols = array_diff($cols, ['rguid','dtadded','addedbyid','addedby','addedbyname','addedbynamear','lastedit','editedbyid','editedby','editedbyname','editedbynamear','omitted']);
        }
        // Filter paired fields by current language for export
        $expCols = [];
        $names = array_column($fields, 'name');
        foreach ($cols as $c) {
            if ($c === $pk) continue;
            $isArField = (strlen($c) > 2 && substr($c, -2) === 'ar');
            $base = $isArField ? substr($c, 0, -2) : $c;
            $paired = $isArField ? in_array($base, $names, true) : in_array($base . 'ar', $names, true);
            if ($paired) {
                if (is_rtl() && $isArField) $expCols[] = $c;
                if (!is_rtl() && !$isArField) $expCols[] = $c;
            } else {
                $expCols[] = $c;
            }
        }
        $cols = array_values($expCols);
        $mainTable = table($table);
        $extraAliases = [];
        if ($extraSelect !== '') {
            preg_match_all('/\bAS\s+(\w+)/i', $extraSelect, $matches);
            $extraAliases = $matches[1] ?? [];
            foreach ($extraAliases as $alias) {
                if (!in_array($alias, $cols)) {
                    $cols[] = $alias;
                }
            }
        }
        $selColsArr = [];
        foreach ($cols as $c) {
            if ($extraFrom !== '' && !in_array($c, $extraAliases) && strpos($c, '.') === false && stripos($c, ' AS ') === false) {
                $selColsArr[] = '`' . $mainTable . '`.`' . $c . '`';
            } else {
                $selColsArr[] = '`' . $c . '`';
            }
        }
        $selCols = implode(', ', $selColsArr);
        if ($extraSelect !== '') {
            $selCols .= ' ' . $extraSelect;
        }
        $where = "`" . table($table) . "`.omitted = 0 $extraWhere";
        $params = [];
        if ($search !== '' && $searchFields) {
            $norm = arabic_normalize($search);
            $conds = [];
            foreach ($searchFields as $sf) {
                $conds[] = "(`$sf` LIKE ? OR `$sf` LIKE ?)";
                $params[] = '%'.$search.'%';
                $params[] = '%'.$norm.'%';
            }
            $where .= ' AND ('.implode(' OR ', $conds).')';
        }
        $rows = fetchAll("SELECT $selCols FROM `".table($table)."` $extraFrom WHERE $where ORDER BY $orderBy", $params);
        $outRows = [];
        foreach ($rows as $r) {
            $out = [];
            foreach ($cols as $c) { $out[] = $r[$c] ?? ''; }
            $outRows[] = $out;
        }
        $headers = [];
        foreach ($cols as $c) {
            $f = null;
            foreach ($allFields as $fd) { if ($fd['name']===$c) { $f=$fd; break; } }
            $headers[] = $f ? (lang()==='ar' && !empty($f['label_ar']) ? $f['label_ar'] : $f['label']) : ucfirst(str_replace('_',' ',$c));
        }
        do_export($fmt, $table.'_export', lang()==='ar'?$titleAr:$title, $headers, $outRows);
        exit;
    }

    // ---------- ADD ----------
    if ($action === 'add') {
        require_permission($module, 'add');
        $data = [];
        foreach ($allFields as $f) { $data[$f['name']] = ''; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $addExtraAliases = [];
            if (!empty($cfg['extra_select'])) {
                preg_match_all('/\bAS\s+(\w+)/i', $cfg['extra_select'], $m);
                $addExtraAliases = $m[1] ?? [];
            }
            $data = [];
            foreach ($allFields as $f) {
                if (!isset($_POST[$f['name']])) continue;
                // Skip extra alias columns (they come from JOINs, not table columns)
                if (in_array($f['name'], $addExtraAliases)) continue;
                $val = $_POST[$f['name']];
                if (($f['type'] ?? '') === 'password' && $val === '') continue;
                if (($f['type'] ?? '') === 'select' && $val === '') $val = null;
                $data[$f['name']] = $val;
            }
            $ok = true;
            foreach ($fields as $f) {
                if (!empty($f['required']) && trim((string)($data[$f['name']] ?? '')) === '') {
                    $ok = false; set_flash('error', 'Required field: '.($f['label'])); break;
                }
            }
            if ($ok) {
                $hook = 'module_'.$table.'_before_save';
                if (function_exists($hook)) { $hook($data, true); }
                $now = date('Y-m-d H:i:s');
                $cols = []; $vals = []; $params = [];
                // rguid must be generated (it's a required unique column, not auto-increment)
                $cols[]='`rguid`'; $vals[]='?'; $params[]=bin2hex(random_bytes(16));
                foreach ($data as $k=>$v) { if (is_audit_col($k)) continue; $cols[]='`'.$k.'`'; $vals[]='?'; $params[]=$v; }
                $cols[]='`dtadded`'; $vals[]='?'; $params[]=$now;
                $cols[]='`addedbyid`'; $vals[]='?'; $params[]=$u['id'];
                $cols[]='`addedby`'; $vals[]='?'; $params[]=$u['username'];
                $an = $u['username_ar'] ?? $u['username'];
                $cols[]='`addedbyname`'; $vals[]='?'; $params[]=$an;
                $cols[]='`addedbynamear`'; $vals[]='?'; $params[]=$an;
                $cols[]='`lastedit`'; $vals[]='?'; $params[]=$now;
                $cols[]='`editedbyid`'; $vals[]='?'; $params[]=$u['id'];
                $cols[]='`editedby`'; $vals[]='?'; $params[]=$u['username'];
                $cols[]='`editedbyname`'; $vals[]='?'; $params[]=$an;
                $cols[]='`editedbynamear`'; $vals[]='?'; $params[]=$an;
                $cols[]='`omitted`'; $vals[]='?'; $params[]=0;
                $sql = 'INSERT INTO `'.table($table).'` ('.implode(',',$cols).') VALUES ('.implode(',',$vals).')';
                if (q($sql, $params)) {
                    $hook = 'module_'.$table.'_after_save';
                    if (function_exists($hook)) { $hook($data, true, lastInsertId()); }
                    set_flash('success', 'Record added successfully.');
                    header('Location: index.php?module='.$module); exit;
                } else {
                    set_flash('error', db_error() ?: 'Failed to add record.');
                }
            }
        }
        echo '<div class="d-flex justify-content-between align-items-center mb-3">
                <h2><i class="bi bi-plus-lg me-2"></i>'.htmlspecialchars(lang()==='ar'?$titleAr:$title).'</h2>
                <a href="index.php?module='.$module.'" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
              </div>';
        echo '<form method="post" action="index.php?module='.$module.'&a=add">';
        foreach ($allFields as $f) { echo field_input($f, $data[$f['name']] ?? ''); }
        echo '<div class="d-grid gap-2"><button class="btn btn-primary">Save</button>
              <a href="index.php?module='.$module.'" class="btn btn-secondary">Cancel</a></div>';
        echo '</form>';
        return;
    }

    // ---------- EDIT ----------
    if ($action === 'edit') {
        require_permission($module, 'edit');
        $row = fetchRow("SELECT * FROM `".table($table)."` WHERE `$pk` = ? AND omitted = 0 LIMIT 1", [$id]);
        if (!$row) { set_flash('error','Record not found.'); header('Location: index.php?module='.$module); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            shadow_log($table, $id, $row, 'update');
            // Extract extra aliases for this handler
            $editExtraAliases = [];
            if (!empty($cfg['extra_select'])) {
                preg_match_all('/\bAS\s+(\w+)/i', $cfg['extra_select'], $m);
                $editExtraAliases = $m[1] ?? [];
            }
            $data = [];
            foreach ($allFields as $f) {
                if (!isset($_POST[$f['name']])) continue;
                // Skip extra alias columns (they come from JOINs, not table columns)
                if (in_array($f['name'], $editExtraAliases)) continue;
                $val = $_POST[$f['name']];
                if (($f['type'] ?? '') === 'password' && $val === '') continue;
                if (($f['type'] ?? '') === 'select' && $val === '') $val = null;
                $data[$f['name']] = $val;
            }
            $ok = true;
            foreach ($fields as $f) {
                if (($f['type'] ?? '') === 'password') continue;
                if (!empty($f['required']) && trim((string)($data[$f['name']] ?? '')) === '') {
                    $ok = false; set_flash('error','Required field: '.($f['label'])); break;
                }
            }
            if ($ok) {
                $hook = 'module_'.$table.'_before_save';
                if (function_exists($hook)) { $hook($data, false, $id, $row); }
                $now = date('Y-m-d H:i:s');
                $sets = []; $params = [];
                foreach ($data as $k=>$v) { if (is_audit_col($k)) continue; $sets[]='`'.$k.'`=?'; $params[]=$v; }
                $an = $u['username_ar'] ?? $u['username'];
                $sets[]='`lastedit`=?'; $params[]=$now;
                $sets[]='`editedbyid`=?'; $params[]=$u['id'];
                $sets[]='`editedby`=?'; $params[]=$u['username'];
                $sets[]='`editedbyname`=?'; $params[]=$an;
                $sets[]='`editedbynamear`=?'; $params[]=$an;
                $params[]=$id;
                $sql = 'UPDATE `'.table($table).'` SET '.implode(',',$sets).' WHERE `'.$pk.'`=?';
                if (q($sql, $params)) {
                    $hook = 'module_'.$table.'_after_save';
                    if (function_exists($hook)) { $hook($data, false, $id, $row); }
                    set_flash('success','Record updated. Previous version saved to audit log.');
                    header('Location: index.php?module='.$module); exit;
                } else {
                    set_flash('error', db_error() ?: 'Failed to update record.');
                }
                $row = array_merge($row, $data);
            }
        }
        echo '<div class="d-flex justify-content-between align-items-center mb-3">
                <h2><i class="bi bi-pencil-square me-2"></i>'.htmlspecialchars(lang()==='ar'?$titleAr:$title).'</h2>
                <a href="index.php?module='.$module.'" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
              </div>';
        echo '<form method="post" action="index.php?module='.$module.'&a=edit&id='.$id.'">';
        foreach ($allFields as $f) { echo field_input($f, $row[$f['name']] ?? ''); }
        echo '<div class="d-grid gap-2"><button class="btn btn-primary">Update</button>
              <a href="index.php?module='.$module.'" class="btn btn-secondary">Cancel</a></div>';
        echo '</form>';
        return;
    }

    // ---------- DELETE (soft omit) ----------
    if ($action === 'del') {
        require_permission($module, 'del');
        $confirm = isset($_GET['confirm']) && $_GET['confirm'] === '1';
        if (!$confirm) {
            echo '<div class="alert alert-warning">
                    <h4>Omit record?</h4>
                    <p>Records are not actually deleted; they are marked as omitted and hidden from reports.</p>
                    <a href="index.php?module='.$module.'&a=del&id='.$id.'&confirm=1" class="btn btn-danger">Yes, Omit</a>
                    <a href="index.php?module='.$module.'" class="btn btn-secondary">Cancel</a>
                  </div>';
            return;
        }
        $row = fetchRow("SELECT * FROM `".table($table)."` WHERE `$pk` = ? LIMIT 1", [$id]);
        if ($row) { shadow_log($table, $id, $row, 'omit'); }
        q("UPDATE `".table($table)."` SET omitted = 1 WHERE `$pk` = ?", [$id]);
        set_flash('success','Record omitted.');
        header('Location: index.php?module='.$module);
        exit;
    }

    // ---------- VIEW (details) ----------
    if ($action === 'view') {
        require_permission($module, 'view');
        $row = fetchRow("SELECT * FROM `".table($table)."` WHERE `$pk` = ? AND omitted = 0 LIMIT 1", [$id]);
        if (!$row) { set_flash('error','Record not found.'); header('Location: index.php?module='.$module); exit; }
        echo '<div class="d-flex justify-content-between align-items-center mb-3">
                <h2><i class="bi bi-eye me-2"></i>'.htmlspecialchars(lang()==='ar'?$titleAr:$title).'</h2>
                <a href="index.php?module='.$module.'" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
              </div>';
        echo '<div class="card"><div class="card-body">';
        $viewFields = fields_for_lang($allFields);
        foreach ($viewFields as $f) {
            $val = $row[$f['name']] ?? '';
            echo '<p><strong>'.htmlspecialchars(lang()==='ar' && !empty($f['label_ar']) ? $f['label_ar'] : $f['label']).':</strong> '.htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>';
        }
        echo '</div></div>';
        if ($canEdit) echo '<a href="index.php?module='.$module.'&a=edit&id='.$id.'" class="btn btn-warning mt-3"><i class="bi bi-pencil me-1"></i>'.(lang()==='ar'?'تعديل':'Edit').'</a>';
        echo ' <a href="index.php?module='.$module.'" class="btn btn-secondary mt-3">'.(lang()==='ar'?'العودة':'Back').'</a>';
        return;
    }

    // ---------- LIST ----------
    require_permission($module, 'view');
    $where = "`" . table($table) . "`.omitted = 0 $extraWhere";
    $params = [];
    if ($search !== '' && $searchFields) {
        $norm = arabic_normalize($search);
        $conds = [];
        foreach ($searchFields as $sf) {
            if ($extraFrom !== '') {
                $conds[] = "(`" . table($table) . "`.`$sf` LIKE ? OR `" . table($table) . "`.`$sf` LIKE ?)";
            } else {
                $conds[] = "(`$sf` LIKE ? OR `$sf` LIKE ?)";
            }
            $params[] = '%'.$search.'%';
            $params[] = '%'.$norm.'%';
        }
        $where .= ' AND ('.implode(' OR ', $conds).')';
    }
    $cols = $listCols ? $listCols : array_column($fields, 'name');
    $cols = array_diff($cols, ['rguid','dtadded','addedbyid','addedby','addedbyname','addedbynamear','lastedit','editedbyid','editedby','editedbyname','editedbynamear','omitted']);
    // Extract extra_select alias columns for display (they come from extra_select, not table columns)
    $extraAliases = [];
    if ($extraSelect !== '') {
        preg_match_all('/\bAS\s+(\w+)/i', $extraSelect, $matches);
        $extraAliases = $matches[1] ?? [];
    }
    // Always include the primary key so edit/delete actions target the right record
    if (!in_array($pk, $cols, true)) {
        array_unshift($cols, $pk);
    }
    $cols = array_values($cols);
    $listLabels = $cfg['list_labels'] ?? [];
    // Display columns: filter paired fields by current language (pk always hidden)
    $displayCols = [];
    foreach ($cols as $c) {
        if ($c === $pk) continue;
        $isArField = (strlen($c) > 2 && substr($c, -2) === 'ar');
        $base = $isArField ? substr($c, 0, -2) : $c;
        $names = array_column($fields, 'name');
        $paired = $isArField ? in_array($base, $names, true) : in_array($base . 'ar', $names, true);
        if ($paired) {
            if (is_rtl() && $isArField) $displayCols[] = $c;
            if (!is_rtl() && !$isArField) $displayCols[] = $c;
        } else {
            $displayCols[] = $c;
        }
    }

    // Add extra_select alias columns to display list (they come from extra_select, not table columns)
    // Only add the language-appropriate one for paired fields
    foreach ($extraAliases as $alias) {
        if ($alias === $pk) continue;
        // Check if it's a paired field
        $isArAlias = (strlen($alias) > 2 && substr($alias, -2) === 'ar');
        $baseAlias = $isArAlias ? substr($alias, 0, -2) : $alias;
        $counterpart = $isArAlias ? $baseAlias : $baseAlias . 'ar';
        $hasCounterpart = in_array($counterpart, $extraAliases, true) || in_array($counterpart, $names, true);
        if ($hasCounterpart) {
            // Paired: only show in appropriate language
            if (is_rtl() && $isArAlias) {
                if (!in_array($alias, $displayCols)) $displayCols[] = $alias;
            }
            if (!is_rtl() && !$isArAlias) {
                if (!in_array($alias, $displayCols)) $displayCols[] = $alias;
            }
        } else {
            // Unpaired: always show
            if (!in_array($alias, $displayCols)) $displayCols[] = $alias;
        }
    }
    $displayCols = array_values($displayCols);
    $mainTable = table($table);
    // Extract alias names from extra_select (e.g., "re.label AS reqexam_label" -> "reqexam_label")
    $extraAliases = [];
    if ($extraSelect !== '') {
        preg_match_all('/\bAS\s+(\w+)/i', $extraSelect, $matches);
        $extraAliases = $matches[1] ?? [];
    }
    // Build select columns - prefix with table name if there's a JOIN to avoid ambiguity
    // but don't prefix extra_select alias columns
    $selColsArr = [];
    foreach ($cols as $c) {
        if ($extraFrom !== '' && !in_array($c, $extraAliases) && strpos($c, '.') === false && stripos($c, ' AS ') === false) {
            $selColsArr[] = '`' . $mainTable . '`.`' . $c . '`';
        } else {
            $selColsArr[] = '`' . $c . '`';
        }
    }
    $selCols = implode(', ', $selColsArr);
    if ($extraSelect !== '') {
        $selCols .= ' ' . $extraSelect;
    }
    $rows = fetchAll("SELECT $selCols FROM `".$mainTable."` $extraFrom WHERE $where ORDER BY $orderBy", $params);

    $dispTitle = lang()==='ar' ? $titleAr : $title;
    echo '<div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="bi bi-table me-2"></i>'.htmlspecialchars($dispTitle).'</h2>
            <div>
              <a href="index.php?module='.$module.'&a=export&fmt=csv" class="btn btn-outline-secondary btn-sm me-1"><i class="bi bi-file-earmark-csv me-1"></i>CSV</a>
              <a href="index.php?module='.$module.'&a=export&fmt=xlsx" class="btn btn-outline-success btn-sm me-1"><i class="bi bi-file-earmark-excel me-1"></i>XLSX</a>
              <a href="index.php?module='.$module.'&a=export&fmt=docx" class="btn btn-outline-primary btn-sm me-1"><i class="bi bi-file-earmark-word me-1"></i>Word</a>
              <a href="index.php?module='.$module.'&a=export&fmt=pdf" class="btn btn-outline-danger btn-sm me-1"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
              '.($canAdd ? '<a href="index.php?module='.$module.'&a=add" class="btn btn-primary ms-2"><i class="bi bi-plus me-1"></i>Add New</a>' : '').'
            </div>
          </div>';
    echo '<form method="get" class="row g-2 mb-3 align-items-end">
            <input type="hidden" name="module" value="'.$module.'">
            <div class="col-md-4"><input type="search" name="search" class="form-control" value="'.htmlspecialchars($search).'" placeholder="Search (Arabic/English)..."></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Search</button></div>
            <div class="col-md-2"><a href="index.php?module='.$module.'" class="btn btn-outline-secondary w-100">Reset</a></div>
          </form>';
    if (count($rows) === 0) {
        echo '<div class="alert alert-info">No records found.</div>';
    } else {
        echo '<div class="table-responsive"><table class="table table-striped table-hover align-middle">';
        echo '<thead class="table-dark"><tr>';
        foreach ($displayCols as $c) {
            $th = $c;
            if (isset($listLabels[$c])) {
                $th = lang() === 'ar' ? ($listLabels[$c]['ar'] ?? $listLabels[$c]['en']) : $listLabels[$c]['en'];
            } else {
                foreach ($allFields as $fd) { if ($fd['name']===$c) { $th = (lang()==='ar' && !empty($fd['label_ar']) ? $fd['label_ar'] : $fd['label']); break; } }
            }
            echo '<th>'.htmlspecialchars($th).'</th>';
        }
        echo '<th>Actions</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr>';
            foreach ($displayCols as $c) {
                $v = $r[$c] ?? '';
                echo '<td>'.htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</td>';
            }
            $recId = $r[$pk] ?? 0;
            echo '<td class="d-flex gap-1">';
            echo '<a href="index.php?module='.$module.'&a=view&id='.$recId.'" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a>';
            if ($canEdit) echo '<a href="index.php?module='.$module.'&a=edit&id='.$recId.'" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>';
            if ($module === 'users' && $canEdit) echo '<a href="index.php?module='.$module.'&a=reset-otp&id='.$recId.'" class="btn btn-sm btn-secondary" onclick="return confirm(\'Reset OTP for this user?\')" title="Reset OTP"><i class="bi bi-key"></i></a>';
            if ($canDel) echo '<a href="index.php?module='.$module.'&a=del&id='.$recId.'" class="btn btn-sm btn-danger" onclick="return confirm(\'Omit this record?\')"><i class="bi bi-trash"></i></a>';
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}
