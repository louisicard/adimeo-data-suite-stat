<?php

namespace AdimeoDataSuite\Stat;

use AdimeoDataSuite\Index\StatIndexManager;

class ResponseTimeStatCompiler extends StatCompiler
{
  function getDisplayName()
  {
    return "Response time";
  }

  function compile($mapping, $from, $to, $period)
  {
    $query = '
      {
      "query": {
          "bool": {
              "must": [{
                "term": {
                  "mapping": "' . $mapping . '"
                }
              }]
          }
      },
      "aggs": {
          "response_time": {
              "range": {
                  "field": "response_time",
                  "ranges": [
                      {
                          "to": 10
                      },
                      {
                          "from": 10,
                          "to": 50
                      },
                      {
                          "from": 50,
                          "to": 100
                      },
                      {
                          "from": 100,
                          "to": 200
                      },
                      {
                          "from": 200,
                          "to": 500
                      },
                      {
                          "from": 500
                      }
                  ]
              }
          }
      }
    }';
    $query = json_decode($query, TRUE);
    if($from != null) {
      $query['query']['bool']['must'][] = json_decode('{
                    "range": {
                        "date": {
                            "gte": "' . $from->format('Y-m-d\TH:i') . '"
                        }
                    }
                }', TRUE);
    }
    if($to != null) {
      $query['query']['bool']['must'][] = json_decode('{
                    "range": {
                        "date": {
                            "lte": "' . $to->format('Y-m-d\TH:i') . '"
                        }
                    }
                }', TRUE);
    }

    $res = $this->getStatIndexManager()->search(StatIndexManager::APP_INDEX_NAME, $query, 0, 9999, 'stat');
    if(isset($res['aggregations']['response_time']['buckets'])){
      $data = array();
      foreach($res['aggregations']['response_time']['buckets'] as $bucket){
        $data[] = array(
          $bucket['key'],
          $bucket['doc_count']
        );
      }
      $this->setData($data);
    }

  }

  function getHeaders()
  {
    return array('Response time in ms', 'Count');
  }

  function getGoogleChartClass()
  {
    return 'google.visualization.ColumnChart';
  }

  function getJSData()
  {
    $js = 'var statData = new google.visualization.DataTable();
    statData.addColumn("string", "Response time in ms");
    statData.addColumn("number", "Count");

    statData.addRows([';

    $first = true;
    //Data
    foreach($this->getData() as $data){
      if($data[0] != null && !empty($data[0]) && $data[1] != null && !empty($data[1])) {
        if (!$first)
          $js .= ',';
        $first = false;
        $js .= '["' . $data[0] . '", ' . $data[1] . ']';
      }
    }

    $js .= ']);';

    $js .= 'var chartOptions = {
          title: "Response time in ms",
          legend: { position: "bottom" }
        };';
    return $js;
  }


}