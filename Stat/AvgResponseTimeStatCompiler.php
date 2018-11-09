<?php

namespace AdimeoDataSuite\Stat;


use AdimeoDataSuite\Index\StatIndexManager;

class AvgResponseTimeStatCompiler extends StatCompiler
{
  function getDisplayName()
  {
    return "Average response time by period";
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
            "date": {
                "date_histogram": {
                    "field": "date",
                    "interval": "' . $this->getElasticPeriod($period) . '"
                },
                "aggs": {
                    "avg_response_time": {
                        "avg": {
                            "field": "response_time"
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
                        "date": {
                            "lte": "' . $to->format('Y-m-d\TH:i') . '"
                        }
                    }
                }', TRUE);
    }

    $res = $this->getStatIndexManager()->search(StatIndexManager::APP_INDEX_NAME, $query, 0, 9999, 'stat');

    if(isset($res['aggregations']['date']['buckets'])){
      $data = array();
      foreach($res['aggregations']['date']['buckets'] as $bucket){
        $data[] = array(
          \DateTime::createFromFormat('Y-m-d\TH:i:s.000\Z', $bucket['key_as_string'])->format('Y-m-d H:i'),
          $bucket['avg_response_time']['value']
        );
      }
      $this->setData($data);
    }

  }

  function getHeaders()
  {
    return array('Date/time', 'Average response time');
  }

  function getGoogleChartClass()
  {
    return 'google.visualization.LineChart';
  }

  function getJSData()
  {
    $js = 'var statData = new google.visualization.DataTable();
    statData.addColumn("datetime", "Date/time");
    statData.addColumn("number", "Average response time");

    statData.addRows([';

    $first = true;
    //Data
    foreach($this->getData() as $data){
      if($data[0] != null && !empty($data[0]) && $data[1] != null && !empty($data[1])) {
        if(!$first)
          $js .= ',';
        $first = false;
        $js .= '[new Date("' . $data[0] . '"), ' . $data[1] . ']';
      }
    }

    $js .= ']);';

    $js .= 'var chartOptions = {
          title: "Average response time",
          legend: { position: "bottom" }
        };';
    return $js;
  }


}