<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 12/16/18
 * Time: 5:01 PM
 */

require_once(ABSPATH . 'wp-content/plugins/submit-code/core/collection/TestCase.php');


class SubmitTemplate {
    private $test_case_array = [];
    private $lang_id = [15 => 'C/C++ (g++ 4.8.5)', 16 => 'C# (mono 5.4.0.167)', 26 => 'Java (JDK 9)', 30 => 'JavaScript (nodejs 8.5.0)', 34 => 'Python (3.6.0)', 33 => 'Pascal (fpc 3.0.0)'];
    private $all_test_case = '';
    private $solution = '';

    private function customTrim($input) {
        $result = str_replace("\r", '', $input);
        $result = str_replace("\t", '', $result);
        $result = str_replace("\0", '', $result);
        $result = str_replace("\x0B", '', $result);
        $result = str_replace("\\n", "\n", $result);
        $result = trim($result, "\n");
        return $result;
    }

    private function parseTestCase() {
        //echo $this->all_test_case.'<br>';
        $test_case = '';
        $i = 0;
        while ($i < strlen($this->all_test_case) - strlen('[/out]')) {
            if ($this->all_test_case[$i + 1] === '[' && $this->all_test_case[$i + 2] === '/' && $this->all_test_case[$i + 3] === 'o' &&
                $this->all_test_case[$i + 4] === 'u' && $this->all_test_case[$i + 5] === 't' && $this->all_test_case[$i + 6] === ']') {
                $test_case .= '[/out]';
                $test_case = trim($test_case);
                //echo $test_case.'<br>';
                $input = str_replace('[inp]', '', strstr($test_case, '[/inp]', true));
                $output = str_replace('[out]', '', str_replace('[/out]', '', strstr($test_case, '[out]', false)));
                $input = $this->customTrim($input);
                $output = $this->customTrim($output);
                $input = json_encode($input);
                $output = json_encode($output);
                $this->test_case_array[] = new TestCase($input, $output);
                $test_case = '';
                $i += strlen('[/out]') + strlen(' ');
            } else {
                $test_case .= $this->all_test_case[$i];
                $i++;
            }
        }
        /* foreach($this->test_case_array as $value){
             echo $value->input.'<br>';
             echo $value->output.'<br>';
         }*/

    }

    function getConentByTag($str, $startTag, $endTag) {
        $start = 0;
        if (!empty($startTag)) $start = mb_strpos($str, $startTag);
        $end = mb_strpos($str, $endTag);
        $result = mb_substr($str, $start, $end - $start);
        $result = str_replace($startTag, '', $result);
        $result = str_replace($endTag, '', $result);
        trim($result);
        return $result;
    }

    function encodeTextPreTag($textToScan) {
        // the regex pattern (case insensitive & multiline
        $search = "~<pre>(.*?)</pre>~is";

// first look for all CODE tags and their content
        preg_match_all($search, $textToScan, $matches);
//print_r($matches);

// now replace all the CODE tags and their content with a htmlspecialchars() content
        foreach ($matches[1] as $match) {
            $replace = htmlspecialchars($match);
            // now replace the previously found CODE block
            $textToScan = str_replace($match, $replace, $textToScan);
        }
        return $textToScan;
    }

    function addFilterContent() {
        add_filter('the_content', function ($content) {
            $this->solution = $this->getConentByTag($content, '[solution]', '[/solution]');
            $this->solution = $this->encodeTextPreTag($this->solution);

            // init post, length
            $pos_start = 0;
            $pos_end = mb_strpos($content, '[test-case]');
            $pos_last = mb_strpos($content, '[/test-case]');
            $content_length = mb_strlen($content);
            $test_length = mb_strlen(mb_substr($content, $pos_end));

            if ($pos_end == false || $pos_last == false) {
                return $content;
            }

            if (is_single()) {
                require plugin_dir_path(__FILE__) . '../assets/style.php';
                $new_content = '';
                // test case string
                $this->all_test_case = mb_substr($content, $pos_end, $test_length);
                $this->all_test_case = str_replace('[test-case]', '', $this->all_test_case);
                $this->all_test_case = str_replace('[/test-case]', '', $this->all_test_case);
                trim($this->all_test_case);
                $this->parseTestCase();
                // content string without test case
                $content = mb_substr($content, $pos_start, $content_length - $test_length);
                $new_content .= $content;
                if (is_user_logged_in()) {
                    return $new_content;
                } else {
                    return $new_content;
                }
            } else {
                $content = mb_substr($content, $pos_start, $content_length - $test_length);
                return $content;
            }
        }, 0);

    }

    function addFilterSubmit() {
        add_filter('the_content', function ($content) {
            if (empty($this->all_test_case))
                return $content;

            echo $content;
            if (is_single() && is_user_logged_in()) {
                $this->template();
            } else {
                $suggestLogin = '<p>Bạn chưa đăng nhập? <b><a style="color: #364956" href="' . get_site_url() . '/login">Đăng nhập </a></b>để Submit ngay!</p>';
                return $suggestLogin;
            }
        });
    }

    private function requestCheckApiLive() {
        echo '<script>
                    $.ajax({
                        method: "GET",
                        url: "' . get_site_url() . '/wp-content/plugins/submit-code/request/requestCheckApiLive.php",
                        timeout: 10000,
                    })
                    .done(async function(data) {
                        console.log(data);
                        if (data.httpCode !== 200)
                            await $(".submit-result").append("<p class=wrong>API is under maintenance, please come back later</p>");
                    })
                    .fail(async function(jqXHR, textStatus, errorThrown) {
                        await $(".submit-result").append("<p class=wrong>API is under maintenance, please come back later</p>");
                    });
            </script>';
    }

    private function template() {
        $this->requestCheckApiLive();
        echo '<textarea id="code-editor" name="source" required></textarea>';
        echo '<select name="lang_id" class="lang_id">';
        foreach ($this->lang_id as $lang_id => $lang_name) {
            if ($_COOKIE['lang_id'] == $lang_id)
                echo '<option value="' . $lang_id . '" selected>' . $lang_name . '</option>';
            else
                echo '<option value="' . $lang_id . '">' . $lang_name . '</option>';
        }
        echo '</select>';
        echo '<button onclick="submit_code()" class="submit-code-btn">Submit</button>';
        echo '<p></p>';
        echo '<div class="submit-result"></div>';
        echo '<button class="button-show-submit-history button-for-submit" onclick="show_submit_history()">Show submit history</button>';
        echo '<span id="id-button-show-solution" style="margin-left: 15px"></span>';
        echo '<div class="submit-history-result"></div>';

        echo '<script>
                            let clicked = 0;
                            let clickShowSubmitHistory = 0;
                            let input = new Array();
                            let output = new Array();
                        </script>';

        foreach ($this->test_case_array as $value) {
            echo '<script> input.push(' . $value->input . ') </script>';
            echo '<script> output.push(' . $value->output . ') </script>';
        }

        echo '<script>
                    function b64DecodeUnicode(str) {
                        return decodeURIComponent(atob(str).split(\'\').map(function(c) {
                                return \'%\' + (\'00\' + c.charCodeAt(0).toString(16)).slice(-2);
                            }).join(\'\'));
                    }
                   
                   function b64EncodeUnicode(str) {
                        return btoa(encodeURIComponent(str).replace(/%([0-9A-F]{2})/g,
                            function toSolidBytes(match, p1) {
                                return String.fromCharCode(\'0x\' + p1);
                        }));
                    }
                </script>';

        echo '<script>
                    const In_Queue = 1;
                    const Processing = 2;
                    const Accepted = 3;
                    const Wrong_Answer = 4;
                    const Time_Limit_Exceeded = 5;
                    const Compilation_Error = 6;
                    const Runtime_Error_SIGSEGV_ = 7;
                    const Runtime_Error_SIGXFSZ = 8;
                    const Runtime_Error_SIGFPE = 9;
                    const Runtime_Error_SIGABRT = 10;
                    const Runtime_Error_NZEC = 11;
                    const Runtime_Error_Other = 12;
                    const Internal_Error = 13;
                    
                    
                    let myCodeMirror = CodeMirror.fromTextArea(document.getElementById("code-editor"), {
                                            lineNumbers: true,
                                            theme: "material"
                                          });
                    
                    function anti(input) {
                        let output = input;
                        output = output.replace(/</g, "");
                        output = output.replace(/>/g, "");
                        output = output.replace(/script/g, "");   
                        return output;
                    }
                                        
                    async function submit_code() {
                        let source_code = myCodeMirror.getValue()
                        if (source_code.trim() !== "")
                            clicked++;
                        
                        let count_unit_test = 1;
                        let total = input.length;
                        let pass = 0;
                        let err = 0;
                        let active = 1;
                        
                        await $(".submit-code-btn").css("color: while")
                        let lang_id = await $(".lang_id").find(":selected").val();
                        
                        if (clicked === 1) {
                            await $(".submit-code-btn").text("Wait...");

                            await $( ".submit-result" ).empty();
                            if (source_code.trim() !== ""){
                                for (let i=0; i< input.length; i++){
                                    if (err === 1 || active === 0)
                                        break;
                                    
                                    await $(".submit-result").append("<p class=accepted id=on-load-test>"+count_unit_test+". Running...</p>");
                                 
                                    await $.ajax({
                                              method: "POST",
                                              url: "' . get_site_url() . '/wp-content/plugins/submit-code/request/requestJudge0Api.php",
                                              data: {
                                                  source: b64EncodeUnicode(source_code),
                                                  stdin: b64EncodeUnicode(input[i]),
                                                  expected_output: b64EncodeUnicode(output[i]),
                                                  lang_id: lang_id
                                               }
                                            })
                                          .done(async function(data) {
                                              console.log("data: " + data);
                                               
                                              if (data.hasOwnProperty("activated")) {
                                                  active = 0;
                                                  await $("#on-load-test").remove();
                                                  await $(".submit-result").append("<p class=wrong>Please active plugin</p>");
                                                  await $(".submit-code-btn").text("Submit");
                                                  clicked = 0;
                                              } else {
                                                                                 
                                                  let description = data.status.description;
                                                  let status_id = data.status.id;
                                                  let expected_output = output[i];
                                                  
                                                  console.log("status_id: " + status_id)
                                                                                               
                                                  if (status_id === Compilation_Error || status_id === Runtime_Error_NZEC 
                                                  || status_id === Runtime_Error_SIGSEGV_ || status_id === Runtime_Error_SIGXFSZ
                                                  || status_id === Runtime_Error_SIGFPE || status_id === Runtime_Error_SIGABRT
                                                  || status_id === Runtime_Error_Other){
                                                        err = 1;      
                                                        await $("#on-load-test").remove();
                                                        await $(".submit-result").append("<p class=wrong>" + description + "</p>");
                                                        if (status_id === Compilation_Error) {
                                                            let complite_output = b64DecodeUnicode(data.compile_output);
                                                            await $(".submit-result").append("<p class=compilation_error>" + anti(complite_output) + "</p>");
                                                        } else {
                                                            let stderr = b64DecodeUnicode(data.stderr);
                                                            await $(".submit-result").append("<p class=compilation_error>" + stderr + "</p>");
                                                        }
                                                  } else if (status_id !== Accepted && status_id !== Wrong_Answer && status_id !== Internal_Error){
                                                        await $("#on-load-test").remove();
                                                        await $(".submit-result").append("<p class=wrong>" + description + "</p>");
                                                  }                    
                                                      
                                                  if (status_id === Accepted) {
                                                        pass++;
                                                        await $("#on-load-test").remove();
                                                        await $(".submit-result").append("<p class=accepted>"+count_unit_test+". " + description + "</p>");                              
                                                  }
                                                  
                                                  if (status_id === Wrong_Answer){
                                                      let your_ouput = "";
                                                        if (data.stdout !== null)
                                                            your_ouput = b64DecodeUnicode(data.stdout.trim());
                                                        else 
                                                            your_ouput = "";
                                                        
                                                        console.log("Your output: " + your_ouput);
                                                        await $("#on-load-test").remove();
                                                        await $(".submit-result").append("<p class=wrong>" + count_unit_test + ". "+ description +"</p>");
                                                        await $(".submit-result").append("<pre class=pre-result><span class=result-title>Test Input:</span> \n" +
                                                                                        "" + input[i] + "\n" +
                                                                                        "<span class=result-title>Test Output:</span>\n" + expected_output + "\n" +
                                                                                        "<span class=result-title>Your Output:<code></span>\n" + anti(your_ouput) + "</pre></code>");
                                                  }
                                                  
                                                  if (status_id === Internal_Error){
                                                        let your_ouput = null
                                                        console.log(your_ouput)
                                                        await $("#on-load-test")    .remove();
                                                        await $(".submit-result").append("<p class=wrong>"+count_unit_test+". " + description + " (No Output)</p>");
                                                        await $(".submit-result").append("<pre class=pre-result><span class=result-title>Test Input:</span> \n" +
                                                                                        "" + input[i] + "\n" +
                                                                                        "<span class=result-title>Test Output:</span>\n"+ expected_output + "\n" +
                                                                                        "<span class=result-title>Your Output:</span>\n" + anti(your_ouput) + "</pre>");
                                                  } 
                                              }
                                              
                                          })
                                          .fail(async function(jqXHR, textStatus, errorThrown) {
                                              err = 1;
                                              await $("#on-load-test").remove();
                                              await $(".submit-result").append("<p class=wrong>Error connect</p>");
                                              await $(".submit-code-btn").text("Submit");
                                              clicked = 0;
                                              console.log(errorThrown)
                                          });
                                    
                                    count_unit_test++;
                                }
                                
                                await $(".submit-result").append("<br><br>");
                                
                                if (pass === total)
                                    await $(".submit-result").append("<h4 class=accepted> Passed: " + pass + "/" + total + "</h4>");
                                else
                                    await $(".submit-result").append("<h4 class=Wrong> Passed: " + pass + "/" + total + "</h4>");
                                
                                console.log("active: " + active);
                                if (active === 1) { 
                                    await $.ajax({
                                        method: "POST",
                                        url: "' . get_site_url() . '/wp-content/plugins/submit-code/request/requestSaveSourceCode.php",
                                        data: {
                                             post_id: "' . get_the_ID() . '",
                                             author: "' . wp_get_current_user()->user_login . '",
                                             email: "' . wp_get_current_user()->user_email . '",
                                             source: b64EncodeUnicode(source_code),
                                             user_id: "' . get_current_user_id() . '",
                                             pass: pass+"/"+total,
                                             lang_id: lang_id
                                        }
                                     })
                                    .done(async function(data) {
                                        if (data.trim() === "1")
                                            console.log("Saved");
                                        else {
                                            console.log("Save Error");
                                            console.log(data);
                                        }
                                    })
                                    .fail(async function(jqXHR, textStatus, errorThrown) {
                                      console.log("Save Error");
                                    });
                                    
                                    await $("#on-load-test").remove();
                                    await $(".submit-code-btn").text("Submit");
                                    clicked = 0;
                                }
                            }
                        }
                    }
                </script>';
        echo '<script>
                        let isCorrect = false;
                        let isShowSolution = false;
                        async function show_submit_history() {
                            clickShowSubmitHistory++;
                            if (clickShowSubmitHistory %2 !== 0) {
                                await $(".submit-history-result").css("display", "block");
                                await $(".button-show-submit-history").text("Hidden submit history");                          
                                await $(".submit-history-result").empty();
                                await $.ajax({
                                    method: "POST",
                                    url: "' . get_site_url() . '/wp-content/plugins/submit-code/request/requestReadSourceCode.php",
                                    data: {
                                        post_id: "' . get_the_ID() . '",
                                        user_id: "' . get_current_user_id() . '"
                                    }
                                })
                                .done(async function(data) {
                                     console.log(data);
                                     for (let i=0; i<data.source.length; i++) { 
                                          let total = data.source[i].pass.substring(data.source[i].pass.indexOf("/") + 1);
                                          let pass = data.source[i].pass.substring(0, data.source[i].pass.length - total.length - 1);
                                          console.log("pass: " + pass + "/" + total);
                                          let showCode = "<span onclick=show_code(this) class=submit-history-result-date button-for-submit id=submit-history-result-date-" + i + ">#" + (data.source.length-i) + ". " + data.source[i].date + ". <span> Pass: " + data.source[i].pass + " (" + data.source[i].lang + ")</span></span>";
                                          await $(".submit-history-result").append(showCode);
                                          if (pass === total) {
                                               $("#submit-history-result-date-"+i).css("color", "green");
                                               isCorrect = true;
                                          } else {
                                                $("#submit-history-result-date-"+i).css("color", "red");
                                          }
                                          await $(".submit-history-result").append("<br>");
                                          
                                          await $("#submit-history-result-date-"+i).append("<pre class=submit-history-result-source id=submit-history-result-source-"+i+"></pre>");
                                          await $("#submit-history-result-source-"+i).text(b64DecodeUnicode(data.source[i].source));
                                     }
                                })
                                .fail(function(jqXHR, textStatus, errorThrown) {
                                     console.log("Error connect");
                                });
                            } else {
                                await $(".submit-history-result").css("display", "none");
                                await $(".button-show-submit-history").text("Show submit history");       
                                await $(".submit-history-result").empty();                               
                            }
                            if (isCorrect && !isShowSolution) {
                                isShowSolution=true;
                                let solutionContent = "' . base64_encode($this->solution) . '";
                                await $("#id-button-show-solution").append("<button class=button-for-submit onclick=show_solution(this) id=button-show-solution>Xem cách giải của hệ thống</button>");
                                await $("#button-show-solution").append("<pre class=submit-history-result-source id=id-show-solution></pre>");
                                await $("#id-show-solution").text(solutionContent);
                            }
                        }
                        
                        show_submit_history();
                        show_submit_history();
                </script>';

        echo '<div id="myModal" class="modal">                    
                            <div class="modal-content">
                                <span class="close">&times;</span>
                                <p class="history-result-pass"></p>
                                <div class="solution-content"></div>
                                <textarea class="history-result-source"></textarea>
                            </div>
                    </div>';

        echo '<script>
                            let myCodeMirror2 = CodeMirror.fromTextArea(document.getElementsByClassName("history-result-source")[0], {
                                            lineNumbers: false,
                                            theme: "material"
                                       });
                    </script>';

        echo '<script>
                           async function show_code(obj) {
                               let tagPre = obj.getElementsByTagName("pre")[0];
                               let tagSpan = obj.getElementsByTagName("span")[0];
                               let source = tagPre.textContent;
                               let pass = tagSpan.textContent;
                               await $(".modal").css("display", "block");
                               await $(".history-result-source").text(source);
                               await $  (".history-result-pass").text(pass);
                               myCodeMirror2.getDoc().setValue(source);
                           }
                    </script>';

        echo '<script>
                           async function show_solution(obj) {
                               let tagPre = obj.getElementsByTagName("pre")[0];                              
                               let content = tagPre.textContent;
                               content = b64DecodeUnicode(content);
                               if(!content) content = "We will update solution soon";
                                var win = window.open("", "Solution", "toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=780,height=500,top="+(screen.height-400)+",left="+(screen.width-840));
                                win.document.body.innerHTML = content;
                           }
                    </script>';

        echo '<script>
                    let modal = document.getElementById("myModal");                
                    let btn = document.getElementById("myBtn");                    
                    let span = document.getElementsByClassName("close")[0];
                    
                    span.onclick = function() {
                        modal.style.display = "none";
                        $(".history-result-source").text("");
                    }
                    
                    window.onclick = function(event) {
                        if (event.target === modal) {
                            modal.style.display = "none";
                            $(".history-result-source").text("");
                        }
                    }
                    </script>';
    }

}