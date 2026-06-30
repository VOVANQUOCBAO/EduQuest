<?php require_once __DIR__.'/includes/functions.php'; require_role(['admin','teacher']); $page_title='Bài học';
if(isset($_GET['delete'])){$id=(int)$_GET['delete'];$st=db()->prepare('SELECT name FROM lessons WHERE id=?');$st->execute([$id]);$name=(string)$st->fetchColumn();db()->prepare('DELETE FROM lessons WHERE id=?')->execute([$id]);log_activity('delete','lesson',$id,'Đã xóa bài học: '.($name?:('#'.$id))); flash('Đã xóa bài học'); redirect('lessons.php');}
if($_SERVER['REQUEST_METHOD']==='POST'){
    $id=(int)($_POST['id']??0);
    $subjectId=(int)post('subject_id');
    $name=post('name');
    if($id){db()->prepare('UPDATE lessons SET subject_id=?,name=? WHERE id=?')->execute([$subjectId,$name,$id]);log_activity('update','lesson',$id,'Đã cập nhật bài học: '.$name);}
    else {db()->prepare('INSERT INTO lessons(subject_id,name,created_by) VALUES(?,?,?)')->execute([$subjectId,$name,current_user()['id']]);log_activity('create','lesson',(int)db()->lastInsertId(),'Đã tạo bài học: '.$name);}
    flash('Đã lưu bài học'); redirect('lessons.php');
}
$edit=null;if(isset($_GET['edit'])){$st=db()->prepare('SELECT * FROM lessons WHERE id=?');$st->execute([(int)$_GET['edit']]);$edit=$st->fetch();}
$subjects=fetch_subjects(); $rows=fetch_lessons(); include __DIR__.'/includes/header.php'; ?>
<div class="grid grid-2"><div class="card"><h2><?= $edit?'Sửa':'Thêm' ?> bài học</h2><form method="post"><input type="hidden" name="id" value="<?= e($edit['id']??0) ?>"><label>Môn</label><select name="subject_id"><?php foreach($subjects as $s): ?><option value="<?= $s['id'] ?>" <?= ($edit['subject_id']??0)==$s['id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select><label>Tên bài học</label><input name="name" required value="<?= e($edit['name']??'') ?>"><br><br><button class="btn primary">Lưu</button></form></div><div class="card"><h2>Danh sách bài học</h2><table class="table"><tr><th>Bài</th><th>Môn</th><th></th></tr><?php foreach($rows as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e($r['subject_name']) ?></td><td class="actions"><a class="btn ghost" href="?edit=<?= $r['id'] ?>">Sửa</a><a class="btn danger" data-confirm="Xóa bài học?" href="?delete=<?= $r['id'] ?>">Xóa</a></td></tr><?php endforeach; ?></table></div></div>
<?php include __DIR__.'/includes/footer.php'; ?>
