亲爱的<?php echo $display_name; ?>：
<br />
<br />
您的密码重设要求已经得到验证。请点击以下链接输入您新的密码：
<br />
<br />
(please click on the following link to reset your password:)
<br />
<br />
{{ URL::to('app/index.html#root/resetPassword') }}?confirmation={{ $confirmed }}
<br />
<br />
如果以上链接不能点击，你可以复制网址url，然后粘贴到浏览器地址栏打开。
<br />
<br />
（本链接将在12小时后失效）
<br />
（这是一封自动发送的邮件，请不要直接回复）
<br />
<br />
SAFARI_SHI
<br />
<?php echo date('Y-m-d H:i:s', time()); ?>