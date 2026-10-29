$svc = app(\App\Services\Admin\PermissionService::class);
$created = $svc->create(['name'=>'导出测试','label_en'=>'Test Export','code'=>'admin:product:testexport','group'=>'product','action'=>'testexport','type'=>2,'remark'=>'测试权限']);
echo "CREATE snow_id={$created['snow_id']} label=".json_encode($created['label'],JSON_UNESCAPED_UNICODE)."\n";
$upd = $svc->update($created['snow_id'], ['name'=>'导出测试V2','label_en'=>'Test Export V2','remark'=>'改备注']);
echo "UPDATE label=".json_encode($upd['label'],JSON_UNESCAPED_UNICODE)."\n";
$svc->delete($created['snow_id']);
echo "DELETED ok\n";