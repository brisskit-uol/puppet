			</td>
		</tr>
		</table>
	</td>
</tr>
</table>
<br><br>

<?php 
// REDCap Hook injection point
Hooks::call('redcap_control_center', array());

// Footer
$objHtmlPage->PrintFooter();