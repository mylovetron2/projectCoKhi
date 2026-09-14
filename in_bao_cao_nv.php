<?php
error_reporting(E_ERROR | E_PARSE);

require_once "db.php";
session_start();

if (version_compare(PHP_VERSION, '5.1.0') >= 0) {
    if (ini_get('date.timezone') == '') {
        date_default_timezone_set('UTC');
    }
}

$check   = (isset($_GET['baoduong'])) ? $_GET['baoduong'] : '0';
$tungay  = isset($_GET['tungay'])  ? trim($_GET['tungay'])  : '';
$denngay = isset($_GET['denngay']) ? trim($_GET['denngay']) : '';

// Định dạng ngày hiển thị dd/mm/yyyy
function fmt_date($d) {
    return ($d && $d != '0000-00-00') ? date('d/m/Y', strtotime($d)) : '';
}

// ---- Queries ----
$main   = array();
$stt    = 0;
$so_dv  = 1;

if ($tungay !== '' && $denngay !== '') {
    $tungay_s  = mysqli_real_escape_string($conn, $tungay);
    $denngay_s = mysqli_real_escape_string($conn, $denngay);
    $check_i   = (int)$check;

    $sql = "SELECT ck_don_hang.*, ck_chitiet_suachua.nhan_vien_id
            FROM ck_don_hang
            INNER JOIN ck_danhmuc_suachua ON ck_don_hang.id = ck_danhmuc_suachua.id_don_hang
            INNER JOIN ck_chitiet_suachua  ON ck_danhmuc_suachua.sua_chua_id = ck_chitiet_suachua.sua_chua_id
            WHERE baoduong_dinhky = $check_i
              AND ck_don_hang.ngay_sua_chua BETWEEN '$tungay_s' AND '$denngay_s'
            GROUP BY ck_don_hang.so_don_hang_id
            ORDER BY ck_don_hang.ngay_sua_chua, ck_don_hang.so_don_hang_id";

    if ($result = $conn->query($sql)) {
        while ($row = mysqli_fetch_assoc($result)) {
            $don_hang_id = $row['so_don_hang_id'];
            $stt++;

            $sub_sql = "SELECT ck_danhmuc_thietbi.bo_phan,
                               ck_danhmuc_thietbi.ky_ma_hieu,
                               ck_chungloai_thietbi.ten_chungloai,
                               GROUP_CONCAT(DISTINCT view_nhan_vien.ten_nhan_vien) AS nhan_vien,
                               SUM(ck_view_nhatky.thoi_gian) AS tong_gio,
                               ck_danhmuc_thietbi.thiet_bi_id,
                               ck_view_nhatky.ngay_hoan_thanh
                        FROM ck_view_nhatky
                        INNER JOIN ck_danhmuc_thietbi   ON ck_view_nhatky.thiet_bi_id    = ck_danhmuc_thietbi.thiet_bi_id
                        INNER JOIN ck_chungloai_thietbi ON ck_chungloai_thietbi.chungloai_id = ck_danhmuc_thietbi.chung_loai_id
                        INNER JOIN view_nhan_vien       ON ck_view_nhatky.nhan_vien_id   = view_nhan_vien.nhan_vien_id
                        WHERE ck_view_nhatky.so_don_hang_id = $don_hang_id
                        GROUP BY ck_view_nhatky.thiet_bi_id, ck_danhmuc_thietbi.thiet_bi_id";

            $sub = array();
            $so_dv_ct = 1;
            if ($resultsub = $conn->query($sub_sql)) {
                while ($subrow = mysqli_fetch_assoc($resultsub)) {
                    $sub[] = array(
                        'so_dv_ct'       => $so_dv . '.' . $so_dv_ct,
                        'ten_chungloai'  => $subrow['ten_chungloai'],
                        'ky_ma_hieu'     => $subrow['ky_ma_hieu'],
                        'bo_phan'        => $subrow['bo_phan'],
                        'nhan_vien'      => $subrow['nhan_vien'],
                        'tong_gio'       => $subrow['tong_gio'],
                        'ngay_hoan_thanh'=> $subrow['ngay_hoan_thanh'],
                    );
                    $so_dv_ct++;
                }
            }

            $main[] = array(
                'stt'            => $stt,
                'so_dv'          => $so_dv,
                'so_don_hang_id' => $row['so_don_hang_id'],
                'noi_dung'       => $row['noi_dung_sua_chua'],
                'ngay_sua_chua'  => $row['ngay_sua_chua'],
                'sub'            => $sub,
            );
            $so_dv++;
        }
    }

    // Thống kê
    $sql_stats = "SELECT
                    COUNT(DISTINCT ck_don_hang.so_don_hang_id) AS so_don_hang,
                    COUNT(DISTINCT ck_danhmuc_suachua.thiet_bi_id) AS tong_thiet_bi
                  FROM ck_don_hang
                  INNER JOIN ck_danhmuc_suachua ON ck_don_hang.id = ck_danhmuc_suachua.id_don_hang
                  INNER JOIN ck_chitiet_suachua  ON ck_danhmuc_suachua.sua_chua_id = ck_chitiet_suachua.sua_chua_id
                  WHERE baoduong_dinhky = $check_i
                    AND ck_danhmuc_suachua.thoi_gian_sua_chua != ''
                    AND ck_don_hang.ngay_sua_chua BETWEEN '$tungay_s' AND '$denngay_s'";
    $stats = mysqli_fetch_assoc(mysqli_query($conn, $sql_stats));
    $so_don_hang   = $stats['so_don_hang'];
    $tong_thiet_bi = $stats['tong_thiet_bi'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Báo Cáo Nhân Viên</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css">
<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js"></script>
<style>
    .topnav { overflow:hidden; background-color:#28A745; }
    .topnav a { float:left; color:#f2f2f2; padding:14px 16px; text-decoration:none; font-size:17px; }
    .topnav a:hover { background-color:#ddd; color:black; }
    table { font-size:13px; }
    th { white-space: nowrap; }
    @media print { .no-print { display:none; } body { margin:5mm; } }
</style>
</head>
<body>

<div class="topnav no-print">
    <a class="active" href="/CoKhi/">Home</a>
</div>

<div class="container-fluid" style="padding:20px;">
    <h4>Tra cứu</h4>

    <form action="in_bao_cao_nv.php" method="get" class="no-print">
        <div class="form-row align-items-end">
            <div class="col-auto">
                <label>Từ ngày</label>
                <input type="date" class="form-control form-control-sm" name="tungay"
                       value="<?php echo htmlspecialchars($tungay); ?>">
            </div>
            <div class="col-auto">
                <label>Đến ngày</label>
                <input type="date" class="form-control form-control-sm" name="denngay"
                       value="<?php echo htmlspecialchars($denngay); ?>">
            </div>
            <div class="col-auto" style="padding-top:28px;">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="baoduong" name="baoduong" value="1"
                           <?php echo ($check == 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="baoduong">Bảo dưỡng định kỳ</label>
                </div>
            </div>
            <div class="col-auto" style="padding-top:22px;">
                <button type="submit" class="btn btn-primary btn-sm" style="margin-right:5px;">Tìm kiếm</button>
                <button type="submit" class="btn btn-success btn-sm" formaction="in_excel_ns.php">In Báo Cáo (Excel)</button>
            </div>
        </div>
    </form>

    <?php if ($tungay !== '' && $denngay !== ''): ?>

    <h5 class="text-center mt-3">LIỆT KÊ CÔNG TÁC BẢO DƯỠNG, SỬA CHỮA, CHUẨN CHỈNH THIẾT BỊ</h5>
    <p class="text-center mb-2">
        Từ ngày: <strong><?php echo fmt_date($tungay); ?></strong>
        &nbsp;&mdash;&nbsp;
        Đến ngày: <strong><?php echo fmt_date($denngay); ?></strong>
        <?php if ($check == 1): ?>
            &nbsp;|&nbsp; <span class="badge badge-info">Bảo dưỡng định kỳ</span>
        <?php endif; ?>
    </p>

    <?php if (empty($main)): ?>
        <div class="alert alert-warning">Không có dữ liệu trong khoảng thời gian đã chọn.</div>
    <?php else: ?>

    <button onclick="window.print()" class="btn btn-secondary btn-sm mb-2 no-print">In trang</button>

    <table class="table table-bordered table-sm">
        <thead class="thead-light">
            <tr>
                <th class="text-center">STT DV</th>
                <th class="text-center">STT CT</th>
                <th class="text-center">Số đơn hàng</th>
                <th>Nội dung</th>
                <th class="text-center">Ngày SC</th>
                <th>Chủng loại</th>
                <th>Thiết bị</th>
                <th>Nhân viên</th>
                <th class="text-center">Tổng giờ</th>
                <th class="text-center">Ngày HT</th>
                <th>Bộ phận</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($main as $m):
            if (!empty($m['sub'])):
                $first = true;
                foreach ($m['sub'] as $s): ?>
                <tr>
                    <td class="text-center"><?php echo $first ? $m['so_dv'] : ''; ?></td>
                    <td class="text-center"><?php echo $s['so_dv_ct']; ?></td>
                    <td class="text-center"><?php echo htmlspecialchars($m['so_don_hang_id']); ?></td>
                    <td><?php echo $first ? htmlspecialchars($m['noi_dung']) : ''; ?></td>
                    <td class="text-center"><?php echo $first ? fmt_date($m['ngay_sua_chua']) : ''; ?></td>
                    <td><?php echo htmlspecialchars($s['ten_chungloai']); ?></td>
                    <td><?php echo htmlspecialchars($s['ky_ma_hieu']); ?></td>
                    <td><?php echo htmlspecialchars($s['nhan_vien']); ?></td>
                    <td class="text-center"><?php echo $s['tong_gio']; ?></td>
                    <td class="text-center"><?php echo fmt_date($s['ngay_hoan_thanh']); ?></td>
                    <td><?php echo htmlspecialchars($s['bo_phan']); ?></td>
                </tr>
                <?php $first = false; endforeach;
            else: ?>
                <tr>
                    <td class="text-center"><?php echo $m['so_dv']; ?></td>
                    <td></td>
                    <td class="text-center"><?php echo htmlspecialchars($m['so_don_hang_id']); ?></td>
                    <td><?php echo htmlspecialchars($m['noi_dung']); ?></td>
                    <td class="text-center"><?php echo fmt_date($m['ngay_sua_chua']); ?></td>
                    <td colspan="6"></td>
                </tr>
            <?php endif; endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="table-secondary">
                <td colspan="3"><strong>Tổng đơn hàng: <?php echo $so_don_hang; ?></strong></td>
                <td colspan="8"><strong>Tổng thiết bị: <?php echo $tong_thiet_bi; ?></strong></td>
            </tr>
        </tfoot>
    </table>
    <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>

<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cơ Khí</title>
<meta charset="utf-8">
<head>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>


<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</head>
<style>   
	.search {
			float: left;
			margin:30px;
			
	}
    .topnav {
        overflow: hidden;
        background-color: #28A745;
    }

    .topnav a {
        float: left;
        color: #f2f2f2;
        text-align: center;
        padding: 14px 16px;
        text-decoration: none;
        font-size: 17px;
    }

    .topnav a:hover {
        background-color: #ddd;
        color: black;
    }

    .topnav a.active {
       
        color: white;
    }
</style>

<body>
<div class="topnav">
  <a class="active" href="/CoKhi6/">Home</a>
  
</div>

<div style="padding:20px">
  <h2>Tra cứu</h2>
  
</div>
<!-- MY CODE   ---------------------------------------------------------------   -->
<?php $check = (isset($_GET['baoduong'])) ? $_GET['baoduong'] : ''; ?>

<div class="container">  
    <form action="in_bao_cao_nv.php"> 
        <div class="row">
            
                <div class="input-group mb-3 w-25">
                    <label>Từ ngày</label>
                    <input type="date" class="form-control" name="tungay">
                </div>
           
            
        </div>
       
        <div class="row">
                <div class="input-group mb-3 w-25">
                    <label>Đến ngày</label>
                    <input type="date" class="form-control" name="denngay" >
                </div>
            </div>
            <div class="row">
            <div class="checkbox">
                <label><input type="checkbox" id="baoduong" name="baoduong" value="1" <?php if($check==1)  echo "checked"; else echo ""; ?>>Bảo dưỡng định kỳ</label>
            
            </div>
            
        </div>
        <div class="row">
            <button type="submit" class="btn btn-primary" formaction="in_excel_ns.php">In Báo Cáo</button>
        </div>    
    </form>

<?php
   
    require_once "db.php";
    session_start();
	// Include classes
	include_once('tbs_class.php');

	// prevent from a PHP configuration problem when using mktime() and date()
	if (version_compare(PHP_VERSION,'5.1.0')>=0) {
		if (ini_get('date.timezone')=='') {
			date_default_timezone_set('UTC');
		}
	}
    
    $check = (isset($_GET['baoduong'])) ? $_GET['baoduong'] : '0';

    $tungay = (isset($_GET['tungay'])) ? $_GET['tungay'] : '';
    $tungay = trim(''.$tungay);
	
    $denngay = (isset($_GET['denngay'])) ? $_GET['denngay'] : '';
    $denngay = trim(''.$denngay);

    $old_date=explode('-',$tungay);
    $new_date=$old_date[2].'-'.$old_date[1].'-'.$old_date[0];
	
    $old_date=explode('-',$denngay);
    $new_date2=$old_date[2].'-'.$old_date[1].'-'.$old_date[0];

    $sql="	SELECT ck_don_hang.*, ck_chitiet_suachua.nhan_vien_id
		FROM ck_don_hang INNER JOIN
  		ck_danhmuc_suachua ON ck_don_hang.id = ck_danhmuc_suachua.id_don_hang
  		INNER JOIN
  		ck_chitiet_suachua ON ck_danhmuc_suachua.sua_chua_id =
    	ck_chitiet_suachua.sua_chua_id
		where baoduong_dinhky=".$check." and ck_don_hang.ngay_sua_chua between '".$tungay."' and '".$denngay."'
		Group by ck_don_hang.so_don_hang_id
	";

    $main=array();
    $stt=0;
    $temp=array();
    $sub=array();
    $so_dv=1;

    if ($result = $conn -> query($sql)) {
        while ($row = mysqli_fetch_array($result)) {
            $so_dv_ct=1;
            $main[$stt] = array('stt'=>$stt+1,
                                'noi_dung'=>$row['noi_dung_sua_chua'],
                                'ngay_sua_chua'=>$row['ngay_sua_chua'],
                                'so_don_hang_id'=>$row['so_don_hang_id'],
                                
                                'so_dv'=>$so_dv
                            );
	
            //$sub_sql="SELECT *,SUM(thoi_gian) as tong_gio FROM ck_view_nhatky  WHERE so_don_hang_id=".$row['so_don_hang_id']." Group by thiet_bi_id";
            $sub_sql="SELECT ck_danhmuc_thietbi.bo_phan, ck_danhmuc_thietbi.ky_ma_hieu,
                        ck_chungloai_thietbi.ten_chungloai,
                        Group_Concat(DISTINCT view_nhan_vien.ten_nhan_vien) as nhan_vien, Sum(ck_view_nhatky.thoi_gian) AS
                        tong_gio, ck_view_nhatky.so_don_hang_id, ck_view_nhatky.thoi_gian,
                        ck_view_nhatky.noi_dung, ck_danhmuc_thietbi.thiet_bi_id,
                        ck_view_nhatky.ngay_hoan_thanh
                        FROM ck_view_nhatky INNER JOIN
                        ck_danhmuc_thietbi ON ck_view_nhatky.thiet_bi_id =
                            ck_danhmuc_thietbi.thiet_bi_id INNER JOIN
                        ck_chungloai_thietbi ON ck_chungloai_thietbi.chungloai_id =
                            ck_danhmuc_thietbi.chung_loai_id INNER JOIN
                        view_nhan_vien ON ck_view_nhatky.nhan_vien_id = view_nhan_vien.nhan_vien_id
                        WHERE ck_view_nhatky.so_don_hang_id = ".$row['so_don_hang_id']." 
                        GROUP BY ck_view_nhatky.thiet_bi_id, ck_danhmuc_thietbi.thiet_bi_id";
	  	 
            if ($resultsub = $conn -> query($sub_sql)){
                while($subrow=mysqli_fetch_array($resultsub)){
                    $sub[]=array(//'ngay_sua_chua'=>$subrow['ngay_sua_chua'],
                                'noi_dung'=>$subrow['noi_dung'],
                                'thiet_bi_id'=>$subrow['thiet_bi_id'],
                                'tong_gio'=>$subrow['tong_gio'],
                                'so_dv_ct'=>$so_dv.".".$so_dv_ct,
                                'nhan_vien'=>$subrow['nhan_vien'],
                                'ten_chungloai'=>$subrow['ten_chungloai'],
                                'ky_ma_hieu'=>$subrow['ky_ma_hieu'],
                                'bo_phan'=>$subrow['bo_phan'],
                                'ngay_hoan_thanh'=>$subrow['ngay_hoan_thanh'],
                                'stt'=>$stt+1
                            );
                    $so_dv_ct++;
                }
            }
            $so_dv++;
            $main[$stt]['sub']=$sub;
            $sub=null;
            $stt++;
	    }
    }

    //$sql_so_don_hang="SELECT COUNT(ngay_sua_chua) as `tong`  FROM `ck_don_hang` where ck_don_hang.ngay_sua_chua between '".$new_date."' and '".$new_date2."' ";

    $sql_so_don_hang="SELECT count(DISTINCT ck_don_hang.so_don_hang_id)
    FROM ck_don_hang INNER JOIN
    ck_danhmuc_suachua ON ck_don_hang.id = ck_danhmuc_suachua.id_don_hang
    INNER JOIN
    ck_chitiet_suachua ON ck_danhmuc_suachua.sua_chua_id =
        ck_chitiet_suachua.sua_chua_id
       where baoduong_dinhky=".$check." and ck_don_hang.ngay_sua_chua between '".$tungay."' and '".$denngay."'
        ";
 
    $row = mysqli_fetch_row( mysqli_query($conn,$sql_so_don_hang));
    $so_don_hang=$row[0];

    //$sql_tong_thiet_bi="SELECT COUNT(DISTINCT(thiet_bi_id)) FROM `ck_danhmuc_suachua` WHERE thoi_gian_sua_chua!=''";
    $sql_tong_thiet_bi=	"SELECT Count(DISTINCT thiet_bi_id)
                            FROM ck_danhmuc_suachua INNER JOIN
                            ck_don_hang ON ck_don_hang.id = ck_danhmuc_suachua.id_don_hang
                            WHERE baoduong_dinhky=".$check." and ck_danhmuc_suachua.thoi_gian_sua_chua!=''
                            and ck_don_hang.ngay_sua_chua between '".$tungay."' and '".$denngay."' 
                        ";
         
    $row = mysqli_fetch_row( mysqli_query($conn,$sql_tong_thiet_bi));
    $tong_thiet_bi=$row[0];
    
	$TBS = new clsTinyButStrong; // new instance of TBS
	$TBS->LoadTemplate('in_bao_cao_nv.html');
    $TBS->MergeBlock('main',$main);
    //$TBS->Show();
    ?>
<!-- MY CODE   ---------------------------------------------------------------   -->

   
</div>
	
</body>




	