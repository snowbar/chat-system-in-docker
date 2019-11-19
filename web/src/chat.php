<!DOCTYPE html>
<!--
   重要なのは，
        databaseと接続できる点
        phpがApacheで動いてる点
        下記のdatabaseクラスのconect_databaseにおいて，docker-compose.ymlファイルで指定した環境変数が利用できる点
        
-->
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>dbForm</title>
</head>
<body>
  <?php
  try {
    $db = new DataBase('mytb');
    $db->conect_database();
    $db->create_table();
  ?>
  <form action="" method="post">
    お名前　：<input type="text" name="name" value="<?php get_value($db,1,$_POST['fix_num'],$_POST['fix_pass']); ?>"></br>
    コメント：<input type="text" name="comments" value="<?php get_value($db,2,$_POST['fix_num'],$_POST['fix_pass']); ?>">
    パスワード：<input type="text" name="comments_pass" value="<?php get_value($db,4,$_POST['fix_num'],$_POST['fix_pass']); ?>">
    <input type="submit" name = "transmit"value="送信"></br>

    <input type = "hidden" name="hidden" value="<?=$_POST['fix_num']?>">
    <input type = "hidden" name="hidden_pass" value="<?=$_POST['fix_pass']?>">

    削除番号：<input type="text" name="delete_num">
    パスワード：<input type="text" name="delete_pass" value="">
    <input type="submit" name="delete" value="削除"></br>

    修正番号：<input type="text" name="fix_num" value="">
    パスワード：<input type="text" name="fix_pass" value="">
    <input type="submit" name = "fix" value="修正">
  </form>
<?php
    //show_form($db);
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
      process_form($db);
    } else {
      $db->print_data();
    }
  } catch (PDOException $e) {
    print "Could't connect to the database: " . $e->getMessage();
  }
  function process_form($db) {
    //編集ボタンが押されたときの処理
    if (isset($_POST['fix'])) {
      if (empty($_POST['fix_num'])) {
        print "番号を入力してください。<br>";
      } elseif ($db->check_num($_POST['fix_num'])) {
        $db->check_pass($_POST['fix_num'],$_POST['fix_pass']) ? print "編集中<br>" : print "パスワードが違います。<br>";
      } else {
        print "番号が存在しません。<br>";
      }
    }
    //消去ボタンが押されたときの処理
    if (isset($_POST['delete'])) {
      if (empty($_POST['delete_num'])) {
        print "番号を入力してください。<br>";
      } elseif (!$db->check_num($_POST['delete_num'])) {
        print "番号が存在しません。<br>";
      } elseif (!$db->check_pass($_POST['delete_num'],$_POST['delete_pass'])) {
        print "パスワードが違います。<br>";
      } else {
        $db->delete($_POST['delete_num']);
      }
    }
    //送信ボタンが押されたときの処理（編集状態かどうかで動作が変わる。）
    if (isset($_POST['transmit'])) {
      if (empty($_POST['name'])) {
        print "名前がありません。<br>";
      } elseif (empty($_POST['comments'])) {
        print "コメントがありません。<br>";
      } elseif (!empty($_POST['hidden']) && $db->check_pass($_POST['hidden'],$_POST['hidden_pass'])) {
        $db->update($_POST['hidden'],$_POST['name'],$_POST['comments'],$_POST['comments_pass']);
      } else {
        $db->insert($db->last_num(),$_POST['name'],$_POST['comments'],$_POST['comments_pass']);
      }
    }
    $db->print_data();
  }
function get_value($db,$mode,$num,$pass){
    if(!empty($num) && !empty($pass) && isset($_POST['fix']) && $db->check_pass($num,$pass)) {
      switch ($mode) {
        case 1:
          echo $db->get_name($num);
          break;
        case 2:
          echo $db->get_comment($num);
          break;
        case 4:
          echo $pass;
          break;
        default:
          break;
        }
    } else {
      switch ($mode) {
        case 1:
          echo 'name';
          break;
        case 2:
          echo "comment";
          break;
        case 4:
          echo "password";
          break;
        default:
          break;
      }
    }
  }
class DataBase {
  private $db;
  private $tbname;
  function __construct($tbname) {
    $this->tbname = htmlentities($tbname);
  }
  function conect_database() {
    $dsn = 'mysql:dbname=mydb;host=db;port=3306';
    $user = 'myname';
    $password = 'mypw';
    $this->db = new PDO($dsn,$user,$password,array(PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING));
  }
  function create_table() {
    $sql = "CREATE TABLE IF NOT EXISTS $this->tbname"
    ." ("
    . "id INT NOT NULL,"
    . "name char(32) NOT NULL,"
    . "comment TEXT,"
    . "password char(32),"
    . "datelog char(32),"
    . "PRIMARY KEY(id)"
    .");";
    $stmt = $this->db->prepare($sql);
    $stmt -> execute();
  }
  function show_table() {
    $sql ='SHOW TABLES';
    $result = $this->db->query($sql);
    foreach ($result as $row){
      echo $row[0];
      echo '<br>';
    }
    echo "<hr>";
  }
  function show_create_table() {
    $sql ="SHOW CREATE TABLE $this->tbname";
    $result = $this->db->prepare($sql);
    $result -> execute();
    foreach ($result as $row){
      echo $row[1];
    }
    echo "<hr>";
  }
  function insert($id,$name,$comment,$password) {
    $sql = "INSERT INTO $this->tbname (id,name, comment, password, datelog) VALUES (:id,:name, :comment, :password, :datelog)";
    $stmt = $this->db -> prepare($sql);
    $name = htmlentities($name ,ENT_QUOTES, 'UTF-8');
    $comment = htmlentities($comment ,ENT_QUOTES, 'UTF-8');
    $password = htmlentities($password ,ENT_QUOTES, 'UTF-8');
    $date = date('Y/m/d H:i:s');
    $stmt -> bindParam(':id', $id, PDO::PARAM_INT);
    $stmt -> bindParam(':name', $name, PDO::PARAM_STR);
    $stmt -> bindParam(':comment', $comment, PDO::PARAM_STR);
    $stmt -> bindParam(':password', $password, PDO::PARAM_STR);
    $stmt -> bindParam(':datelog',$date,PDO::PARAM_STR);
    $stmt -> execute();
  }
  function check_num($id) {
    $sql = "SELECT id FROM $this->tbname WHERE id=:id";
    $stmt = $this->db ->prepare($sql);
    $stmt -> bindParam(':id', $id, PDO::PARAM_STR);
    $stmt -> execute();
    $result = $stmt->fetchColumn();
    return ($result == $id);
  }
  function check_pass($id,$password) {
    $sql = "SELECT password FROM $this->tbname WHERE id=:id";
    $stmt = $this->db ->prepare($sql);
    $stmt -> bindParam(':id', $id, PDO::PARAM_STR);
    $stmt -> execute();
    $result = $stmt->fetchColumn();
    return ($result == $password);
  }
  function print_data() {
    $sql = "SELECT * FROM $this->tbname ORDER BY id";
    $stmt = $this->db ->prepare($sql);
    $stmt -> execute();
    $results = $stmt->fetchAll();
    foreach ($results as $row){
      echo $row['id'].' ';
      echo $row['name'].' ';
      echo $row['comment'].' ';
      //echo $row['password'].' ';
      echo $row['datelog'].'<br>';
    }
  }
  function update($id,$name,$comment,$password) {
      $sql = "update $this->tbname set name=:name,comment=:comment,password=:password, datelog=:datelog where id=:id";
      $stmt = $this->db->prepare($sql);
      $date = date('Y/m/d H:i:s');
      $name = htmlentities($name ,ENT_QUOTES, 'UTF-8');
      $comment = htmlentities($comment ,ENT_QUOTES, 'UTF-8');
      $password = htmlentities($password ,ENT_QUOTES, 'UTF-8');
      $stmt->bindParam(':name', $name, PDO::PARAM_STR);
      $stmt->bindParam(':comment', $comment, PDO::PARAM_STR);
      $stmt->bindParam(':id', $id, PDO::PARAM_INT);
      $stmt->bindParam(':password', $password, PDO::PARAM_STR);
      $stmt->bindParam(':datelog',$date,PDO::PARAM_STR);
      $stmt->execute();
  }
  function delete($id) {
    $sql = "delete from $this->tbname where id=:id";
    $stmt = $this->db->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
  }
  function last_num() {
    $sql = "SELECT id FROM $this->tbname ORDER BY id";
    $stmt = $this->db ->prepare($sql);
    $stmt -> execute();
    $results = $stmt->fetchAll();
    foreach ($results as $row){
      $result = $row['id'];
    }
    if (empty($result)) {
      $result = 0;
    }
    return $result + 1;
  }
  function get_name($id) {
    $sql = "SELECT name FROM $this->tbname WHERE id=:id";
    $stmt = $this->db ->prepare($sql);
    $stmt -> bindParam(':id', $id, PDO::PARAM_STR);
    $stmt -> execute();
    $result = $stmt->fetchColumn();
    return ($result);
  }
  //任意の番号のコメントを取得
  function get_comment($id) {
    $sql = "SELECT comment FROM $this->tbname WHERE id=:id";
    $stmt = $this->db ->prepare($sql);
    $stmt -> bindParam(':id', $id, PDO::PARAM_STR);
    $stmt -> execute();
    $result = $stmt->fetchColumn();
    return ($result);
  }
}
?>
</body>
</html>
