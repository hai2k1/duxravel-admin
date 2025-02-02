<?php

namespace Modules\System\Admin;


use Duxravel\Core\UI\Node;
use Illuminate\Support\Facades\DB;

class Development extends Common
{

    private $data = [];
    private $node = [];

    public function index()
    {
        $startTime = strtotime('-6 day');
        // Views
        $apiNumData = app(\Duxravel\Core\Model\VisitorApi::class)
            ->select(DB::raw('SUM(pv) as value, date as label'))
            ->where('date', '>=', date('Y-m-d', $startTime))
            ->groupBy('date')
            ->get();
        $apiNumData = $apiNumData->each(function ($item) {
            $item['name'] = 'Views';
            return $item;
        });
        $this->data('apiNum', $apiNumData);

        $apiNumChart = (new \Duxravel\Core\Util\Charts)
            ->area()
            ->date(date('Y-m-d', $startTime), date('Y-m-d'), '1 days', 'm-d')
            ->data('Views', $apiNumData->toArray())
            ->height(200)
            ->render(true);
        $this->node['apiNumChart'] = $apiNumChart;

        // access delay
        $apiTimeData = app(\Duxravel\Core\Model\VisitorApi::class)
            ->select(DB::raw('MAX(max_time) as max, MAX(min_time) as min, date as label'))
            ->where('date', '>=', date('Y-m-d', $startTime))
            ->groupBy('date')
            ->get();
        $apiTimeMax = $apiTimeData->map(function ($item) {
            $item['value'] = $item['max'];
            return $item;
        })->toArray();
        $apiTimeMin = $apiTimeData->map(function ($item) {
            $item['value'] = $item['min'];
            return $item;
        })->toArray();
        $this->data('apiTime', collect($apiTimeMax));

        $apiTimeChart = (new \Duxravel\Core\Util\Charts)
            ->line()
            ->date(date('Y-m-d', $startTime), date('Y-m-d'), '1 days', 'm-d')
            ->data('Maximum delay', $apiTimeMax)
            ->data('Minimum delay', $apiTimeMin)
            ->legend(true)
            ->height(200)
            ->render(true);

        $this->node['apiTimeChart'] = $apiTimeChart;

        // File Upload
        $fileNumData = app(\Duxravel\Core\Model\File::class)
            ->select(DB::raw('COUNT(*) as value, DATE_FORMAT(created_at,"%Y-%m-%d")  as label'))
            ->where('created_at', '>=', date('Y-m-d', $startTime))
            //->where('has_type', 'admin')
            ->groupBy(DB::raw('DATE_FORMAT(created_at,"%Y-%m-%d")'))
            ->get();
        $this->data('fileNum', $fileNumData);

        $fileNumChart = (new \Duxravel\Core\Util\Charts)
            ->column()
            ->date(date('Y-m-d', $startTime), date('Y-m-d'), '1 days', 'm-d')
            ->data('number of files', $fileNumData->toArray(), 'Y-m-d')
            ->height(200)
            ->render(true);

        $this->node['fileNumChart'] = $fileNumChart;

        //Operation log
        $operateData = app(\Duxravel\Core\Model\VisitorOperate::class)
            ->select(DB::raw('COUNT(*) as value, DATE_FORMAT(created_at,"%Y-%m-%d")  as label'))
            ->where('created_at', '>=', date('Y-m-d', $startTime))
            ->where('has_type', 'admin')
            ->groupBy(DB::raw('DATE_FORMAT(created_at,"%Y-%m-%d")'))
            ->get();
        $this->data('logNum', $operateData);

        $logNumChart = (new \Duxravel\Core\Util\Charts)
            ->column()
            ->date(date('Y-m-d', $startTime), date('Y-m-d'), '1 days', 'm-d')
            ->data('Operation record', $operateData->toArray(), 'Y-m-d')
            ->render(true);

        $this->node['logNumChart'] = $logNumChart;

        foreach ($this->node as $key => $vo) {
            $this->assign($key, $vo);
        }
        foreach ($this->data as $key => $vo) {
            $this->assign($key, $vo);
        }
        return $this->systemView('vendor/duxphp/duxravel-admin/src/System/View/Admin/Development/index');
    }

    private function data($label, $data)
    {
        $dataTmpLast = $data->last();
        $dataTmpDay = $dataTmpLast['value'];
        $dataTmpSum = $data->sum('value');
        $dataTmpBefore = $data->last(function ($item) use ($dataTmpLast) {
            return $item['label'] < $dataTmpLast['label'];
        })['value'];
        $dataTmpRate = $dataTmpSum ? round($dataTmpDay / $dataTmpSum * 100) : 0;
        $dataTmpTrend = 1;
        if ($dataTmpBefore < $dataTmpDay) {
            $dataTmpTrend = 2;
        }
        if ($dataTmpBefore > $dataTmpDay) {
            $dataTmpTrend = 0;
        }

        $this->data[$label . 'Day'] = $dataTmpDay;
        $this->data[$label . 'Rate'] = $dataTmpRate;
        $this->data[$label . 'Trend'] = $dataTmpTrend;
    }


}
