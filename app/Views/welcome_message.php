<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Welcome to CodeIgniter 4!</title>
    <meta name="description" content="The small framework with powerful features">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/png" href="/favicon.ico">
</head>
<body>

<table>
    <thead>
        <tr>
            <td>#</td>
            <td>Текст</td>
            <td>Дата</td>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($messages as $message): ?>
        <tr>
            <td><?= $message['id'] ?></td>
            <td><?= $message['content'] ?></td>
            <td><?= date('d.m.Y', strtotime($message['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <a href="<?= $pager->getPreviousPageURI() ?>">Назад</a>
    <?php if($pager->getLastPage() !== $pager->getCurrentPage()): ?>
    <a href="<?= $pager->getNextPageURI() ?>">Вперед</a>
    <?php endif; ?>
</table>

</body>
</html>
