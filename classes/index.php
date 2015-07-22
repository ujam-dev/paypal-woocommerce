<!DOCTYPE HTML>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Cell Phone Shop</title>
<style type="text/css">
.items-wrp{
	max-width: 740px;
	padding: 10px;
	margin: 20px auto;
}
.items-wrp ul{
	margin:0px;
	list-style:none;
	padding:0px;
}
.items-wrp ul li{
	list-style: none;
	display: inline-block;
	margin: 10px;
	border: 1px solid #ddd;
}
.items-wrp ul li div.item-name{
text-align: center;
font-weight: bold;
font-size: 18px;
color: #494949;
}
.items-wrp ul li div.btn-wrap{
	display: block;
	text-align: center;
	background: #E0E0E0;
	padding: 5px;
	font-size: 14px;
	text-decoration: none;
	color: #3D3D3D;
	margin-top: 5px;
}
</style>
</head>

<body>

<div class="items-wrp">
<h2 style="text-align:center">My Online Cell Phone Shop</h2>
    <ul>
        <li>
            <img src="images/cell_phone1.jpg" width="220" height="220">
            <div class="item-name">Star mini S5</div> 
            <div class="btn-wrap">
            <form action="order_process.php" method="post">
            Qty:
            <select name="qty">
                <option>1</option>
                <option>2</option>
                <option>3</option>
                <option>4</option>
                <option>5</option>
                <option>8</option>
                <option>10</option>
                <option>20</option>
            </select> 
            <input type="hidden" name="item_name" value="Star mini S5" />
            <input type="hidden" name="item_code" value="001" />
            <input type="hidden" name="item_price" value="100" />
            <input type="submit" value="Buy $100" />
            </form>
            </div>
        </li>
        <li>
            <img src="images/cell_phone2.jpg" width="220" height="220">
            <div class="item-name">Star mini S6</div> 
            <div class="btn-wrap">
            	<form action="order_process.php" method="post">
                Qty:
                <select name="qty">
                    <option>1</option>
                    <option>2</option>
                    <option>3</option>
                    <option>4</option>
                    <option>5</option>
                    <option>8</option>
                    <option>10</option>
                    <option>20</option>
                </select>
                <input type="hidden" name="item_name" value="Star mini S6" />
                <input type="hidden" name="item_code" value="002" />
                <input type="hidden" name="item_price" value="120" />
                <input type="submit" value="Buy $120" />
                </form>
            </div>
        </li>
        <li>
            <img src="images/cell_phone4.jpg" width="220" height="220">
            <div class="item-name">Mpie MP707</div> 
            <div class="btn-wrap">
            	<form action="order_process.php" method="post">
                Qty:
                <select name="qty">
                    <option>1</option>
                    <option>2</option>
                    <option>3</option>
                    <option>4</option>
                    <option>5</option>
                    <option>8</option>
                    <option>10</option>
                    <option>20</option>
                </select>
                <input type="hidden" name="item_name" value="Mpie MP707" />
                <input type="hidden" name="item_code" value="004" />
                <input type="hidden" name="item_price" value="130" />
                <input type="submit" value="Buy $130" />
                </form>
            </div>
        </li>
    </ul>
</div>

</body>
</html>

