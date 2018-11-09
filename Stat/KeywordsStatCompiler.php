<?php

namespace AdimeoDataSuite\Stat;

use AdimeoDataSuite\Index\StatIndexManager;


class KeywordsStatCompiler extends StatCompiler
{
  function getDisplayName()
  {
    return "Keywords statistics";
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
          "keywords": {
              "nested":{
                "path": "query"
              },
              "aggs":{
                "raw": {
                  "terms": {
                      "field": "query.raw"
                  }
                },
                "analyzed": {
                  "terms": {
                      "field": "query.analyzed"
                  }
                }
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
                        "stat_date": {
                            "lte": "' . $to->format('Y-m-d\TH:i') . '"
                        }
                    }
                }', TRUE);
    }
    $query = json_encode($query, JSON_PRETTY_PRINT);

    $res = $this->getStatIndexManager()->search(StatIndexManager::APP_INDEX_NAME, $query, 0, 9999, 'stat');

    if(isset($res['aggregations']['keywords']['analyzed']['buckets'])){
      $data = array();
      foreach($res['aggregations']['keywords']['analyzed']['buckets'] as $bucket){
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
    return array('Keywords', 'Count');
  }

  function getGoogleChartClass()
  {
    return 'google.visualization.ColumnChart';
  }

  function getJSData()
  {
    $js = 'var statData = new google.visualization.DataTable();
    statData.addColumn("string", "Keywords");
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
          title: "Keywords statistics",
          legend: { position: "bottom" }
        };';
    return $js;
  }


}